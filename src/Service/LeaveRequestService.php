<?php

namespace App\Service;

use App\Entity\Rh\Employee;
use App\Entity\Rh\LeaveRequest;
use App\Repository\Rh\EmployeeRepository;
use App\Repository\Rh\LeaveRequestRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class LeaveRequestService
{
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
        return $this->leaveRequestRepository->findByEmployee($employeeId);
    }

    public function getEmployeePendingCount(int $employeeId): int
    {
        return $this->leaveRequestRepository->countPendingByEmployee($employeeId);
    }

    public function submitEmployeeRequest(int $employeeId, string $startDateInput, string $endDateInput, string $leaveType, string $reason): array
    {
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

        $employee = $this->employeeRepository->find($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Employe introuvable.'];
        }

        $leaveRequest = new LeaveRequest();
        $leaveRequest->setEmployee($employee)
            ->setEmployeeName($employee->getFullName())
            ->setStartDate($startDate)
            ->setEndDate($endDate)
            ->setLeaveType(trim($leaveType) !== '' ? trim($leaveType) : 'Conge annuel')
            ->setReason(trim($reason))
            ->setStatus('ATTENTE')
            ->setRequestDate(new DateTimeImmutable('today'))
            ->setDaysCount($workingDays);

        $this->em->persist($leaveRequest);
        $this->em->flush();

        return [
            'success' => true,
            'message' => sprintf('Demande de conge enregistree (%d jours ouvrables).', $workingDays),
        ];
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
        return $this->leaveRequestRepository->countPendingByRh($rhId);
    }

    public function getRhDashboardStats(int $rhId): array
    {
        return $this->leaveRequestRepository->getStatsByRh($rhId);
    }

    public function approveRequestByRh(int $rhId, int $leaveRequestId, string $rhComment = ''): array
    {
        $request = $this->leaveRequestRepository->findOneByRh($leaveRequestId, $rhId);

        if (!$request) {
            return ['success' => false, 'message' => 'Demande introuvable dans votre perimetre.'];
        }

        if ($request->getStatus() !== 'ATTENTE') {
            return ['success' => false, 'message' => 'Seules les demandes en attente peuvent etre approuvees.'];
        }

        $request->setStatus('ACCEPTE');
        $request->setRhComment(trim($rhComment) !== '' ? trim($rhComment) : null);
        $this->em->flush();

        $this->leaveBalanceService->deductApprovedDays($request->getEmployee()->getId(), $request->getDaysCount());

        return ['success' => true, 'message' => 'Demande approuvee avec succes.'];
    }

    public function rejectRequestByRh(int $rhId, int $leaveRequestId, string $rhComment): array
    {
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
        $this->em->flush();

        return ['success' => true, 'message' => 'Demande refusee.'];
    }

    public function getEmployeeDashboardStats(int $employeeId): array
    {
        return $this->leaveRequestRepository->getStatsByEmployee($employeeId);
    }

    public function getRhCreditSummary(int $rhId): array
    {
        return $this->leaveBalanceService->getCreditSummaryByRh($rhId);
    }
}
