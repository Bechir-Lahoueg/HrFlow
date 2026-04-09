<?php

namespace App\Service;

use App\Entity\Rh\Employee;
use App\Entity\Rh\LeaveRequest;
use App\Repository\Rh\EmployeeRepository;
use App\Repository\Rh\LeaveRequestRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class LeaveRequestService
{
    public const CATEGORY_NORMAL = 'NORMAL';
    public const CATEGORY_EXCEPTION = 'EXCEPTION';

    public const WORKFLOW_NORMAL = 'NORMAL';
    public const WORKFLOW_RH_PENDING = 'RH_PENDING';
    public const WORKFLOW_ADMIN_PENDING = 'ADMIN_PENDING';
    public const WORKFLOW_ADMIN_APPROVED = 'ADMIN_APPROVED';
    public const WORKFLOW_RH_REJECTED = 'RH_REJECTED';
    public const WORKFLOW_ADMIN_REJECTED = 'ADMIN_REJECTED';
    public const WORKFLOW_FROZEN_UNPROCESSED = 'FROZEN_UNPROCESSED';

    private const VALID_URGENCY_LEVELS = ['LOW', 'MEDIUM', 'HIGH'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LeaveRequestRepository $leaveRequestRepository,
        private readonly EmployeeRepository $employeeRepository,
        private readonly PublicHolidayService $publicHolidayService,
        private readonly LeaveBalanceService $leaveBalanceService,
    ) {
    }

    /** @return LeaveRequest[] */
    public function getEmployeeRequests(int $employeeId): array
    {
        $this->autoFreezeExpiredExceptionalRequests();
        return $this->leaveRequestRepository->findByEmployee($employeeId);
    }

    public function getEmployeePendingCount(int $employeeId): int
    {
        $this->autoFreezeExpiredExceptionalRequests();
        return $this->leaveRequestRepository->countPendingByEmployee($employeeId);
    }

    public function submitEmployeeRequest(
        int $employeeId,
        string $startDateInput,
        string $endDateInput,
        string $leaveType,
        string $reason,
        string $requestMode = self::CATEGORY_NORMAL,
        ?string $urgencyLevel = null,
        ?string $attachmentPath = null,
    ): array
    {
        $this->autoFreezeExpiredExceptionalRequests();

        $startDate = DateTimeImmutable::createFromFormat('Y-m-d', $startDateInput);
        $endDate = DateTimeImmutable::createFromFormat('Y-m-d', $endDateInput);

        if (!$startDate || !$endDate) {
            return ['success' => false, 'message' => 'Format de date invalide.'];
        }

        $today = new DateTimeImmutable('today');
        if ($startDate < $today) {
            return ['success' => false, 'message' => 'La date de debut ne peut pas etre dans le passe.'];
        }

        if ($endDate < $startDate) {
            return ['success' => false, 'message' => 'La date de fin doit etre apres la date de debut.'];
        }

        if ($this->leaveRequestRepository->hasDateOverlap($employeeId, $startDate, $endDate)) {
            return ['success' => false, 'message' => 'Cette periode chevauche deja une demande en attente ou acceptee.'];
        }

        if ($this->publicHolidayService->hasHolidayInRange($startDate, $endDate)) {
            return ['success' => false, 'message' => 'La periode contient un jour ferie national. Modifiez les dates.'];
        }

        $workingDays = $this->publicHolidayService->countWorkingDays($startDate, $endDate);
        if ($workingDays <= 0) {
            return ['success' => false, 'message' => 'La periode choisie ne contient aucun jour ouvrable.'];
        }

        $requestMode = strtoupper(trim($requestMode));
        $isExceptionRequest = $requestMode === self::CATEGORY_EXCEPTION;

        $balance = $this->leaveBalanceService->getEmployeeBalance($employeeId);
        $availableDays = (float) ($balance['available_days'] ?? 0.0);

        if (!$isExceptionRequest && ($availableDays <= 0 || $workingDays > $availableDays)) {
            return [
                'success' => false,
                'message' => 'Credit insuffisant. Le conge normal est bloque. Envoyez une demande exceptionnelle pour validation RH/Admin.',
            ];
        }

        $employee = $this->employeeRepository->find($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Employe introuvable.'];
        }

        $normalizedUrgency = null;

        if ($isExceptionRequest) {
            if (mb_strlen(trim($reason)) < 15) {
                return ['success' => false, 'message' => 'Pour une demande exceptionnelle, le motif detaille est obligatoire (minimum 15 caracteres).'];
            }

            $normalizedUrgency = strtoupper(trim((string) $urgencyLevel));
            if (!in_array($normalizedUrgency, self::VALID_URGENCY_LEVELS, true)) {
                return ['success' => false, 'message' => 'Niveau d\'urgence invalide.'];
            }
        }

        $leaveRequest = new LeaveRequest();
        $leaveRequest->setEmployee($employee)
            ->setEmployeeName($employee->getFullName())
            ->setStartDate(DateTime::createFromInterface($startDate))
            ->setEndDate(DateTime::createFromInterface($endDate))
            ->setLeaveType(trim($leaveType) !== '' ? trim($leaveType) : 'Conge annuel')
            ->setReason(trim($reason))
            ->setStatus('ATTENTE')
            ->setRequestDate(new DateTime('today'))
            ->setDaysCount($workingDays);

        if ($isExceptionRequest) {
            $leaveRequest->setRequestCategory(self::CATEGORY_EXCEPTION);
            $leaveRequest->setWorkflowStatus(self::WORKFLOW_RH_PENDING);
            $leaveRequest->setUrgencyLevel($normalizedUrgency);
            $leaveRequest->setAttachmentPath($attachmentPath);
            $leaveRequest->appendAuditLog($employee->getFullName(), 'EXCEPTION_REQUEST_CREATED', trim($reason));
        } else {
            $leaveRequest->setRequestCategory(self::CATEGORY_NORMAL);
            $leaveRequest->setWorkflowStatus(self::WORKFLOW_NORMAL);
            $leaveRequest->appendAuditLog($employee->getFullName(), 'NORMAL_REQUEST_CREATED');
        }

        $this->em->persist($leaveRequest);
        $this->em->flush();

        if ($isExceptionRequest) {
            return [
                'success' => true,
                'message' => sprintf('Demande exceptionnelle envoyee (%d jours). En attente de validation RH puis Admin.', $workingDays),
            ];
        }

        return ['success' => true, 'message' => sprintf('Demande de conge enregistree (%d jours ouvrables).', $workingDays)];
    }

    public function deleteEmployeePendingRequest(int $employeeId, int $leaveRequestId): bool
    {
        $request = $this->leaveRequestRepository->findOnePendingByEmployee($leaveRequestId, $employeeId);

        if (!$request) {
            return false;
        }

        $this->em->remove($request);
        $this->em->flush();

        return true;
    }

    /** @return LeaveRequest[] */
    public function getRhRequests(
        int $rhId,
        ?string $statusFilter,
        string $employeeSearch = '',
        string $leaveTypeSearch = '',
        string $search = '',
        string $sort = 'request_date',
        string $direction = 'DESC'
    ): array
    {
        $this->autoFreezeExpiredExceptionalRequests();
        return $this->leaveRequestRepository->findByRh(
            $rhId,
            $statusFilter,
            $employeeSearch,
            $leaveTypeSearch,
            $search,
            $sort,
            $direction
        );
    }

    public function getRhPendingCount(int $rhId): int
    {
        $this->autoFreezeExpiredExceptionalRequests();
        return $this->leaveRequestRepository->countPendingByRh($rhId);
    }

    public function getRhDashboardStats(int $rhId): array
    {
        return $this->leaveRequestRepository->getStatsByRh($rhId);
    }

    public function approveRequestByRh(int $rhId, int $leaveRequestId, string $rhComment = '', string $rhActor = 'RH'): array
    {
        $this->autoFreezeExpiredExceptionalRequests();
        $request = $this->leaveRequestRepository->findOneByRh($leaveRequestId, $rhId);

        if (!$request) {
            return ['success' => false, 'message' => 'Demande introuvable dans votre perimetre.'];
        }

        if ($request->getStatus() !== 'ATTENTE') {
            return ['success' => false, 'message' => 'Seules les demandes en attente peuvent etre approuvees.'];
        }

        if ($request->getRequestCategory() === self::CATEGORY_EXCEPTION) {
            if ($request->getWorkflowStatus() !== self::WORKFLOW_RH_PENDING) {
                return ['success' => false, 'message' => 'Cette demande exceptionnelle n\'est pas dans l\'etape RH.'];
            }

            $request->setWorkflowStatus(self::WORKFLOW_ADMIN_PENDING);
            $request->setRhComment(trim($rhComment) !== '' ? trim($rhComment) : null);
            $request->setRhDecisionAt(new \DateTime('now'));
            $request->setRhDecisionBy($rhActor);
            $request->appendAuditLog($rhActor, 'RH_PRE_APPROVED', $rhComment);
            $this->em->flush();

            return ['success' => true, 'message' => 'Demande exceptionnelle pre-approuvee par RH et envoyee a l\'Admin.'];
        }

        $request->setStatus('ACCEPTE');
        $request->setRhComment(trim($rhComment) !== '' ? trim($rhComment) : null);
        $request->setRhDecisionAt(new \DateTime('now'));
        $request->setRhDecisionBy($rhActor);
        $request->appendAuditLog($rhActor, 'RH_APPROVED', $rhComment);

        $deducted = $this->leaveBalanceService->deductApprovedDays($request->getEmployee()->getId(), $request->getDaysCount(), false);
        if (!$deducted) {
            return ['success' => false, 'message' => 'Credit insuffisant. Utilisez une demande exceptionnelle pour depassement.'];
        }

        $this->em->flush();

        return ['success' => true, 'message' => 'Demande approuvee avec succes.'];
    }

    public function rejectRequestByRh(int $rhId, int $leaveRequestId, string $rhComment, string $rhActor = 'RH'): array
    {
        $this->autoFreezeExpiredExceptionalRequests();
        $request = $this->leaveRequestRepository->findOneByRh($leaveRequestId, $rhId);

        if (!$request) {
            return ['success' => false, 'message' => 'Demande introuvable dans votre perimetre.'];
        }

        if ($request->getStatus() !== 'ATTENTE') {
            return ['success' => false, 'message' => 'Seules les demandes en attente peuvent etre refusees.'];
        }

        if (trim($rhComment) === '') {
            return ['success' => false, 'message' => 'Le commentaire RH est obligatoire pour un refus.'];
        }

        $request->setStatus('REFUSE');
        $request->setRhComment(trim($rhComment));
        $request->setRhDecisionAt(new \DateTime('now'));
        $request->setRhDecisionBy($rhActor);
        if ($request->getRequestCategory() === self::CATEGORY_EXCEPTION) {
            $request->setWorkflowStatus(self::WORKFLOW_RH_REJECTED);
            $request->appendAuditLog($rhActor, 'RH_REJECTED_EXCEPTION', $rhComment);
        } else {
            $request->appendAuditLog($rhActor, 'RH_REJECTED', $rhComment);
        }
        $this->em->flush();

        return ['success' => true, 'message' => 'Demande refusee.'];
    }

    /** @return LeaveRequest[] */
    public function getAdminExceptionRequests(): array
    {
        $this->autoFreezeExpiredExceptionalRequests();
        return $this->leaveRequestRepository->findAdminExceptionPending();
    }

    public function approveExceptionByAdmin(int $leaveRequestId, string $adminComment = '', string $adminActor = 'ADMIN'): array
    {
        $this->autoFreezeExpiredExceptionalRequests();
        $request = $this->leaveRequestRepository->find($leaveRequestId);
        if (!$request || $request->getRequestCategory() !== self::CATEGORY_EXCEPTION) {
            return ['success' => false, 'message' => 'Demande exceptionnelle introuvable.'];
        }

        if ($request->getWorkflowStatus() !== self::WORKFLOW_ADMIN_PENDING) {
            return ['success' => false, 'message' => 'Cette demande n\'est pas en attente de validation Admin.'];
        }

        $request->setStatus('ACCEPTE');
        $request->setWorkflowStatus(self::WORKFLOW_ADMIN_APPROVED);
        $request->setAdminComment(trim($adminComment) !== '' ? trim($adminComment) : null);
        $request->setAdminDecisionAt(new \DateTime('now'));
        $request->setAdminDecisionBy($adminActor);
        $request->appendAuditLog($adminActor, 'ADMIN_APPROVED_EXCEPTION', $adminComment);

        $deducted = $this->leaveBalanceService->deductApprovedDays($request->getEmployee()->getId(), $request->getDaysCount(), true);
        if (!$deducted) {
            return ['success' => false, 'message' => 'Impossible de deduire le solde pour cette demande.'];
        }

        $this->em->flush();
        return ['success' => true, 'message' => 'Demande exceptionnelle approuvee definitivement par Admin.'];
    }

    public function rejectExceptionByAdmin(int $leaveRequestId, string $adminComment, string $adminActor = 'ADMIN'): array
    {
        $this->autoFreezeExpiredExceptionalRequests();
        $request = $this->leaveRequestRepository->find($leaveRequestId);
        if (!$request || $request->getRequestCategory() !== self::CATEGORY_EXCEPTION) {
            return ['success' => false, 'message' => 'Demande exceptionnelle introuvable.'];
        }

        if ($request->getWorkflowStatus() !== self::WORKFLOW_ADMIN_PENDING) {
            return ['success' => false, 'message' => 'Cette demande n\'est pas en attente de validation Admin.'];
        }

        if (trim($adminComment) === '') {
            return ['success' => false, 'message' => 'Le commentaire Admin est obligatoire pour un refus.'];
        }

        $request->setStatus('REFUSE');
        $request->setWorkflowStatus(self::WORKFLOW_ADMIN_REJECTED);
        $request->setAdminComment(trim($adminComment));
        $request->setAdminDecisionAt(new \DateTime('now'));
        $request->setAdminDecisionBy($adminActor);
        $request->appendAuditLog($adminActor, 'ADMIN_REJECTED_EXCEPTION', $adminComment);
        $this->em->flush();

        return ['success' => true, 'message' => 'Demande exceptionnelle refusee par Admin.'];
    }

    public function getEmployeeDashboardStats(int $employeeId): array
    {
        return $this->leaveRequestRepository->getStatsByEmployee($employeeId);
    }

    public function getRhCreditSummary(int $rhId): array
    {
        return $this->leaveBalanceService->getCreditSummaryByRh($rhId);
    }

    /**
     * Returns all dates (Y-m-d) that are blocked because the employee
     * already has an approved or pending leave covering them.
     * @return string[]
     */
    public function getEmployeeBlockedLeaveDates(int $employeeId): array
    {
        $today = new DateTimeImmutable('today');
        $activeLeaves = $this->leaveRequestRepository->findActiveLeavesByEmployee($employeeId, $today);

        $blockedDates = [];
        foreach ($activeLeaves as $leave) {
            $current = new \DateTime($leave->getStartDate()->format('Y-m-d'));
            $end = new \DateTime($leave->getEndDate()->format('Y-m-d'));
            while ($current <= $end) {
                $day = (int) $current->format('N'); // 6=Sat, 7=Sun
                if ($day < 6) {
                    $blockedDates[] = $current->format('Y-m-d');
                }
                $current->modify('+1 day');
            }
        }

        return array_values(array_unique($blockedDates));
    }

    /** @return LeaveRequest[] */
    public function getUpcomingApprovedByRh(int $rhId): array
    {
        $today = new DateTimeImmutable('today');
        return $this->leaveRequestRepository->findUpcomingApprovedByRh($rhId, $today);
    }

    private function autoFreezeExpiredExceptionalRequests(): void
    {
        $today = new DateTimeImmutable('today');
        $requests = $this->leaveRequestRepository->findExpiredExceptionalPending($today);

        if ($requests === []) {
            return;
        }

        foreach ($requests as $request) {
            $startDate = $request->getStartDate();
            $startLabel = $startDate ? $startDate->format('d/m/Y') : 'date inconnue';
            $cause = 'Demande gelee automatiquement: non traitee avant la date de debut prevue (' . $startLabel . ').';

            $request->setStatus('REFUSE');
            $request->setWorkflowStatus(self::WORKFLOW_FROZEN_UNPROCESSED);
            if (!$request->getRhComment()) {
                $request->setRhComment($cause);
            }
            $request->appendAuditLog('SYSTEM', 'AUTO_FROZEN_UNPROCESSED', $cause);
        }

        $this->em->flush();
    }
}
