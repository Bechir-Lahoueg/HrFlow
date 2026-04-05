<?php

namespace App\Service;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final class LeaveRequestService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly PublicHolidayService $publicHolidayService,
        private readonly LeaveBalanceService $leaveBalanceService,
    ) {
    }

    public function getEmployeeRequests(int $employeeId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id, start_date, end_date, leave_type, reason, status, request_date, rh_comment, days_count
             FROM leave_requests
             WHERE employee_id = :employeeId
             ORDER BY request_date DESC, id DESC',
            ['employeeId' => $employeeId]
        );
    }

    public function getEmployeePendingCount(int $employeeId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM leave_requests WHERE employee_id = :employeeId AND status = :status',
            ['employeeId' => $employeeId, 'status' => 'ATTENTE']
        );
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

        if ($this->hasDateOverlap($employeeId, $startDate, $endDate)) {
            return ['success' => false, 'message' => 'Cette periode chevauche deja une demande en attente ou acceptee.'];
        }

        if ($this->publicHolidayService->hasHolidayInRange($startDate, $endDate)) {
            return ['success' => false, 'message' => 'La periode contient un jour ferie national. Modifiez les dates.'];
        }

        $workingDays = $this->publicHolidayService->countWorkingDays($startDate, $endDate);
        if ($workingDays <= 0) {
            return ['success' => false, 'message' => 'La periode choisie ne contient aucun jour ouvrable.'];
        }

        $employee = $this->connection->fetchAssociative(
            'SELECT first_name, last_name FROM employees WHERE id = :employeeId LIMIT 1',
            ['employeeId' => $employeeId]
        );

        if (!$employee) {
            return ['success' => false, 'message' => 'Employe introuvable.'];
        }

        $employeeName = trim(((string) $employee['first_name']) . ' ' . ((string) $employee['last_name']));

        $this->connection->insert('leave_requests', [
            'employee_id' => $employeeId,
            'employee_name' => $employeeName,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'leave_type' => trim($leaveType) !== '' ? trim($leaveType) : 'Conge annuel',
            'reason' => trim($reason),
            'status' => 'ATTENTE',
            'request_date' => (new DateTimeImmutable('today'))->format('Y-m-d'),
            'rh_comment' => null,
            'days_count' => $workingDays,
        ]);

        return [
            'success' => true,
            'message' => sprintf('Demande de conge enregistree (%d jours ouvrables).', $workingDays),
        ];
    }

    public function deleteEmployeePendingRequest(int $employeeId, int $leaveRequestId): bool
    {
        $request = $this->connection->fetchAssociative(
            'SELECT id, status FROM leave_requests WHERE id = :id AND employee_id = :employeeId LIMIT 1',
            ['id' => $leaveRequestId, 'employeeId' => $employeeId]
        );

        if (!$request || (string) $request['status'] !== 'ATTENTE') {
            return false;
        }

        $this->connection->delete('leave_requests', ['id' => $leaveRequestId]);
        return true;
    }

    public function getRhRequests(int $rhId, ?string $statusFilter, string $employeeSearch = '', string $leaveTypeSearch = ''): array
    {
        $sql = 'SELECT lr.id, lr.employee_id, lr.employee_name, lr.start_date, lr.end_date, lr.leave_type, lr.reason, lr.status, lr.request_date, lr.rh_comment, lr.days_count,
                   COALESCE(lb.available_days, 0) AS available_days,
                   COALESCE(lb.total_accrued, 0) AS total_accrued,
                   COALESCE(lb.total_used, 0) AS total_used
                FROM leave_requests lr
                INNER JOIN employees e ON e.id = lr.employee_id
            LEFT JOIN leave_balance lb ON lb.employee_id = lr.employee_id
                WHERE e.rh_id = :rhId';

        $params = ['rhId' => $rhId];

        if ($statusFilter !== null && in_array($statusFilter, ['ATTENTE', 'ACCEPTE', 'REFUSE'], true)) {
            $sql .= ' AND lr.status = :status';
            $params['status'] = $statusFilter;
        }

        if (trim($employeeSearch) !== '') {
            $sql .= ' AND lr.employee_name LIKE :employeeSearch';
            $params['employeeSearch'] = '%' . trim($employeeSearch) . '%';
        }

        if (trim($leaveTypeSearch) !== '') {
            $sql .= ' AND lr.leave_type LIKE :leaveTypeSearch';
            $params['leaveTypeSearch'] = '%' . trim($leaveTypeSearch) . '%';
        }

        $sql .= ' ORDER BY lr.request_date DESC, lr.id DESC';

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    public function getRhPendingCount(int $rhId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*)
             FROM leave_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             WHERE e.rh_id = :rhId AND lr.status = :status',
            ['rhId' => $rhId, 'status' => 'ATTENTE']
        );
    }

    public function getRhDashboardStats(int $rhId): array
    {
        $stats = $this->connection->fetchAssociative(
            'SELECT
                COUNT(*) AS total_count,
                SUM(CASE WHEN lr.status = \'ATTENTE\' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN lr.status = \'ACCEPTE\' THEN 1 ELSE 0 END) AS approved_count,
                SUM(CASE WHEN lr.status = \'REFUSE\' THEN 1 ELSE 0 END) AS rejected_count
             FROM leave_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             WHERE e.rh_id = :rhId',
            ['rhId' => $rhId]
        );

        return [
            'total_count' => (int) ($stats['total_count'] ?? 0),
            'pending_count' => (int) ($stats['pending_count'] ?? 0),
            'approved_count' => (int) ($stats['approved_count'] ?? 0),
            'rejected_count' => (int) ($stats['rejected_count'] ?? 0),
        ];
    }

    public function approveRequestByRh(int $rhId, int $leaveRequestId, string $rhComment = ''): array
    {
        $request = $this->findRhScopedRequest($rhId, $leaveRequestId);

        if (!$request) {
            return ['success' => false, 'message' => 'Demande introuvable dans votre perimetre.'];
        }

        if ((string) $request['status'] !== 'ATTENTE') {
            return ['success' => false, 'message' => 'Seules les demandes en attente peuvent etre approuvees.'];
        }

        $this->connection->update('leave_requests', [
            'status' => 'ACCEPTE',
            'rh_comment' => trim($rhComment) !== '' ? trim($rhComment) : null,
        ], [
            'id' => $leaveRequestId,
        ]);

        $this->leaveBalanceService->deductApprovedDays((int) $request['employee_id'], (int) $request['days_count']);

        return ['success' => true, 'message' => 'Demande approuvee avec succes.'];
    }

    public function rejectRequestByRh(int $rhId, int $leaveRequestId, string $rhComment): array
    {
        $request = $this->findRhScopedRequest($rhId, $leaveRequestId);

        if (!$request) {
            return ['success' => false, 'message' => 'Demande introuvable dans votre perimetre.'];
        }

        if ((string) $request['status'] !== 'ATTENTE') {
            return ['success' => false, 'message' => 'Seules les demandes en attente peuvent etre refusees.'];
        }

        if (trim($rhComment) === '') {
            return ['success' => false, 'message' => 'Le commentaire RH est obligatoire pour un refus.'];
        }

        $this->connection->update('leave_requests', [
            'status' => 'REFUSE',
            'rh_comment' => trim($rhComment),
        ], [
            'id' => $leaveRequestId,
        ]);

        return ['success' => true, 'message' => 'Demande refusee.'];
    }

    public function getEmployeeDashboardStats(int $employeeId): array
    {
        $stats = $this->connection->fetchAssociative(
            'SELECT
                SUM(CASE WHEN status = \'ATTENTE\' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status = \'ACCEPTE\' THEN 1 ELSE 0 END) AS approved_count,
                SUM(CASE WHEN status = \'REFUSE\' THEN 1 ELSE 0 END) AS rejected_count
             FROM leave_requests
             WHERE employee_id = :employeeId',
            ['employeeId' => $employeeId]
        );

        return [
            'pending_count' => (int) ($stats['pending_count'] ?? 0),
            'approved_count' => (int) ($stats['approved_count'] ?? 0),
            'rejected_count' => (int) ($stats['rejected_count'] ?? 0),
        ];
    }

    public function getRhCreditSummary(int $rhId): array
    {
        $stats = $this->connection->fetchAssociative(
            'SELECT
                COALESCE(SUM(lb.available_days), 0) AS available_sum,
                COALESCE(SUM(lb.total_used), 0) AS used_sum,
                COALESCE(SUM(lb.total_accrued), 0) AS accrued_sum,
                COUNT(e.id) AS employees_count
             FROM employees e
             LEFT JOIN leave_balance lb ON lb.employee_id = e.id
             WHERE e.rh_id = :rhId',
            ['rhId' => $rhId]
        );

        return [
            'available_sum' => (float) ($stats['available_sum'] ?? 0),
            'used_sum' => (float) ($stats['used_sum'] ?? 0),
            'accrued_sum' => (float) ($stats['accrued_sum'] ?? 0),
            'employees_count' => (int) ($stats['employees_count'] ?? 0),
        ];
    }

    private function hasDateOverlap(int $employeeId, DateTimeImmutable $startDate, DateTimeImmutable $endDate): bool
    {
        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*)
             FROM leave_requests
             WHERE employee_id = :employeeId
                             AND status IN (\'ATTENTE\', \'ACCEPTE\')
               AND NOT (end_date < :startDate OR start_date > :endDate)',
            [
                'employeeId' => $employeeId,
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d'),
            ]
        );

        return $count > 0;
    }

    private function findRhScopedRequest(int $rhId, int $leaveRequestId): array|false
    {
        return $this->connection->fetchAssociative(
            'SELECT lr.id, lr.employee_id, lr.status, lr.days_count
             FROM leave_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             WHERE lr.id = :id AND e.rh_id = :rhId
             LIMIT 1',
            ['id' => $leaveRequestId, 'rhId' => $rhId]
        );
    }
}
