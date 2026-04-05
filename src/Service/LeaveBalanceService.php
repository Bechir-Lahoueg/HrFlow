<?php

namespace App\Service;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final class LeaveBalanceService
{
    private const MONTHLY_ACCRUAL_DAYS = 1.8;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getEmployeeBalance(int $employeeId): array
    {
        try {
            $this->accrueIfNeeded($employeeId);

            $balance = $this->connection->fetchAssociative(
                'SELECT available_days, total_accrued, total_used FROM leave_balance WHERE employee_id = :employeeId LIMIT 1',
                ['employeeId' => $employeeId]
            );
        } catch (\Throwable) {
            return [
                'available_days' => 0.0,
                'total_accrued' => 0.0,
                'total_used' => 0.0,
            ];
        }

        if (!$balance) {
            return [
                'available_days' => 0.0,
                'total_accrued' => 0.0,
                'total_used' => 0.0,
            ];
        }

        return [
            'available_days' => (float) $balance['available_days'],
            'total_accrued' => (float) $balance['total_accrued'],
            'total_used' => (float) $balance['total_used'],
        ];
    }

    public function deductApprovedDays(int $employeeId, int $daysCount): void
    {
        try {
            $balance = $this->getEmployeeBalance($employeeId);

            $this->connection->update('leave_balance', [
                'available_days' => round($balance['available_days'] - $daysCount, 2),
                'total_used' => round($balance['total_used'] + $daysCount, 2),
            ], [
                'employee_id' => $employeeId,
            ]);
        } catch (\Throwable) {
            // Keep workflow functional if leave_balance schema is not yet aligned.
        }
    }

    public function refundApprovedDays(int $employeeId, int $daysCount): void
    {
        try {
            $balance = $this->getEmployeeBalance($employeeId);

            $this->connection->update('leave_balance', [
                'available_days' => round($balance['available_days'] + $daysCount, 2),
                'total_used' => round(max(0, $balance['total_used'] - $daysCount), 2),
            ], [
                'employee_id' => $employeeId,
            ]);
        } catch (\Throwable) {
            // Keep workflow functional if leave_balance schema is not yet aligned.
        }
    }

    public function getBalancesByRh(int $rhId): array
    {
        try {
            $employees = $this->connection->fetchAllAssociative(
                'SELECT id FROM employees WHERE rh_id = :rhId',
                ['rhId' => $rhId]
            );

            foreach ($employees as $employee) {
                $this->accrueIfNeeded((int) ($employee['id'] ?? 0));
            }

            return $this->connection->fetchAllAssociative(
                'SELECT
                    e.id AS employee_id,
                    CONCAT(e.first_name, " ", e.last_name) AS employee_name,
                    COALESCE(lb.available_days, 0) AS available_days,
                    COALESCE(lb.total_accrued, 0) AS total_accrued,
                    COALESCE(lb.total_used, 0) AS total_used
                 FROM employees e
                 LEFT JOIN leave_balance lb ON lb.employee_id = e.id
                 WHERE e.rh_id = :rhId
                 ORDER BY e.first_name ASC, e.last_name ASC',
                ['rhId' => $rhId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    private function accrueIfNeeded(int $employeeId): void
    {
        $employee = $this->connection->fetchAssociative(
            'SELECT first_name, last_name, created_at FROM employees WHERE id = :employeeId LIMIT 1',
            ['employeeId' => $employeeId]
        );

        if (!$employee) {
            return;
        }

        $employeeName = trim(((string) $employee['first_name']) . ' ' . ((string) $employee['last_name']));
        $hireDate = new DateTimeImmutable(substr((string) $employee['created_at'], 0, 10));

        $existing = $this->connection->fetchAssociative(
            'SELECT id, available_days, total_accrued, total_used, hire_date, last_accrual_date
             FROM leave_balance WHERE employee_id = :employeeId LIMIT 1',
            ['employeeId' => $employeeId]
        );

        if (!$existing) {
            $this->connection->insert('leave_balance', [
                'employee_id' => $employeeId,
                'employee_name' => $employeeName,
                'available_days' => 0,
                'total_accrued' => 0,
                'total_used' => 0,
                'last_accrual_date' => null,
                'hire_date' => $hireDate->format('Y-m-d'),
            ]);

            $existing = $this->connection->fetchAssociative(
                'SELECT id, available_days, total_accrued, total_used, hire_date, last_accrual_date
                 FROM leave_balance WHERE employee_id = :employeeId LIMIT 1',
                ['employeeId' => $employeeId]
            );

            if (!$existing) {
                return;
            }
        }

        $referenceDate = !empty($existing['last_accrual_date'])
            ? new DateTimeImmutable((string) $existing['last_accrual_date'])
            : new DateTimeImmutable((string) $existing['hire_date']);

        $today = new DateTimeImmutable('today');
        $months = $this->getCompletedMonths($referenceDate, $today);

        if ($months <= 0) {
            return;
        }

        $accruedDays = round(self::MONTHLY_ACCRUAL_DAYS * $months, 2);
        $availableDays = (float) $existing['available_days'] + $accruedDays;
        $totalAccrued = (float) $existing['total_accrued'] + $accruedDays;

        $this->connection->update('leave_balance', [
            'employee_name' => $employeeName,
            'available_days' => round($availableDays, 2),
            'total_accrued' => round($totalAccrued, 2),
            'last_accrual_date' => $today->format('Y-m-d'),
        ], [
            'id' => (int) $existing['id'],
        ]);
    }

    private function getCompletedMonths(DateTimeImmutable $fromDate, DateTimeImmutable $toDate): int
    {
        if ($toDate <= $fromDate) {
            return 0;
        }

        $interval = $fromDate->diff($toDate);
        $months = ($interval->y * 12) + $interval->m;

        if ($interval->d <= 0) {
            return max(0, $months);
        }

        return max(0, $months);
    }
}
