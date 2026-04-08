<?php

namespace App\Service;

use App\Entity\Rh\LeaveBalance;
use App\Repository\Rh\EmployeeRepository;
use App\Repository\Rh\LeaveBalanceRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class LeaveBalanceService
{
    private const MONTHLY_ACCRUAL_DAYS = 1.8;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LeaveBalanceRepository $leaveBalanceRepository,
        private readonly EmployeeRepository $employeeRepository,
    ) {
    }

    public function getEmployeeBalance(int $employeeId): array
    {
        try {
            $this->accrueIfNeeded($employeeId);

            $balance = $this->leaveBalanceRepository->findByEmployee($employeeId);
        } catch (\Throwable) {
            return ['available_days' => 0.0, 'total_accrued' => 0.0, 'total_used' => 0.0];
        }

        if (!$balance) {
            return ['available_days' => 0.0, 'total_accrued' => 0.0, 'total_used' => 0.0];
        }

        return [
            'available_days' => $balance->getAvailableDays(),
            'total_accrued' => $balance->getTotalAccrued(),
            'total_used' => $balance->getTotalUsed(),
        ];
    }

    public function deductApprovedDays(int $employeeId, int $daysCount): void
    {
        try {
            $balance = $this->leaveBalanceRepository->findByEmployee($employeeId);
            if (!$balance) {
                return;
            }

            $balance->setAvailableDays($balance->getAvailableDays() - $daysCount);
            $balance->setTotalUsed($balance->getTotalUsed() + $daysCount);
            $this->em->flush();
        } catch (\Throwable) {
        }
    }

    public function refundApprovedDays(int $employeeId, int $daysCount): void
    {
        try {
            $balance = $this->leaveBalanceRepository->findByEmployee($employeeId);
            if (!$balance) {
                return;
            }

            $balance->setAvailableDays($balance->getAvailableDays() + $daysCount);
            $balance->setTotalUsed(max(0, $balance->getTotalUsed() - $daysCount));
            $this->em->flush();
        } catch (\Throwable) {
        }
    }

    public function getBalancesByRh(int $rhId): array
    {
        try {
            $employees = $this->employeeRepository->findBy(['rhId' => $rhId]);

            foreach ($employees as $employee) {
                $this->accrueIfNeeded($employee->getId());
            }

            return $this->leaveBalanceRepository->findByRh($rhId);
        } catch (\Throwable) {
            return [];
        }
    }

    public function getCreditSummaryByRh(int $rhId): array
    {
        try {
            return $this->leaveBalanceRepository->getCreditSummaryByRh($rhId);
        } catch (\Throwable) {
            return ['available_sum' => 0.0, 'used_sum' => 0.0, 'accrued_sum' => 0.0, 'employees_count' => 0];
        }
    }

    private function accrueIfNeeded(int $employeeId): void
    {
        $employee = $this->employeeRepository->find($employeeId);
        if (!$employee) {
            return;
        }

        $balance = $this->leaveBalanceRepository->findByEmployee($employeeId);

        if (!$balance) {
            $hireDate = $employee->getCreatedAt()
                ? new DateTimeImmutable($employee->getCreatedAt()->format('Y-m-d'))
                : new DateTimeImmutable('today');

            $balance = new LeaveBalance();
            $balance->setEmployee($employee)
                ->setEmployeeName($employee->getFullName())
                ->setAvailableDays(0)
                ->setTotalAccrued(0)
                ->setTotalUsed(0)
                ->setHireDate($hireDate);

            $this->em->persist($balance);
            $this->em->flush();
        }

        $referenceDate = $balance->getLastAccrualDate()
            ? new DateTimeImmutable($balance->getLastAccrualDate()->format('Y-m-d'))
            : ($balance->getHireDate()
                ? new DateTimeImmutable($balance->getHireDate()->format('Y-m-d'))
                : new DateTimeImmutable('today'));

        $today = new DateTimeImmutable('today');
        $months = $this->getCompletedMonths($referenceDate, $today);

        if ($months <= 0) {
            return;
        }

        $accruedDays = round(self::MONTHLY_ACCRUAL_DAYS * $months, 2);

        $balance->setEmployeeName($employee->getFullName());
        $balance->setAvailableDays($balance->getAvailableDays() + $accruedDays);
        $balance->setTotalAccrued($balance->getTotalAccrued() + $accruedDays);
        $balance->setLastAccrualDate($today);

        $this->em->flush();
    }

    private function getCompletedMonths(DateTimeImmutable $fromDate, DateTimeImmutable $toDate): int
    {
        if ($toDate <= $fromDate) {
            return 0;
        }

        $interval = $fromDate->diff($toDate);

        return max(0, ($interval->y * 12) + $interval->m);
    }
}
