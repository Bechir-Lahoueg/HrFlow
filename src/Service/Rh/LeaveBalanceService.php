<?php

namespace App\Service\Rh;

use App\Entity\Rh\LeaveBalance;
use App\Repository\Rh\EmployeeRepository;
use App\Repository\Rh\LeaveBalanceRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class LeaveBalanceService
{
    private const MONTHLY_ACCRUAL_DAYS = 1.8;

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LeaveBalanceRepository $leaveBalanceRepository,
        private readonly EmployeeRepository $employeeRepository,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /** @return array{available_days: float, total_accrued: float, total_used: float} */
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

    public function deductApprovedDays(int $employeeId, int $daysCount, bool $allowNegative = false): bool
    {
        try {
            $balance = $this->leaveBalanceRepository->findByEmployee($employeeId);
            if (!$balance) {
                return false;
            }

            if (!$allowNegative && $balance->getAvailableDays() < $daysCount) {
                return false;
            }

            $balance->setAvailableDays($balance->getAvailableDays() - $daysCount);
            $balance->setTotalUsed($balance->getTotalUsed() + $daysCount);
            $this->em->flush();
            return true;
        } catch (\Throwable) {
            return false;
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

    /** @return LeaveBalance[] */
    public function getBalancesByRh(int $rhId): array
    {
        try {
            // 1 query: load all employees for this RH
            $employees = $this->employeeRepository->findBy(['rhId' => $rhId]);
            if (empty($employees)) {
                return [];
            }

            // 1 query: load all existing balances (with employee already joined/selected)
            $existingBalances = $this->leaveBalanceRepository->findByRh($rhId);
            $balanceByEmployeeId = [];
            foreach ($existingBalances as $lb) {
                $emp = $lb->getEmployee();
                if ($emp !== null && $emp->getId() !== null) {
                    $balanceByEmployeeId[$emp->getId()] = $lb;
                }
            }

            $today = new DateTimeImmutable('today');
            $needsFlush = false;

            foreach ($employees as $employee) {
                $employeeId = $employee->getId();
                if ($employeeId === null) {
                    continue;
                }

                if (!isset($balanceByEmployeeId[$employeeId])) {
                    // Create missing balance record
                    $hireDate = $employee->getCreatedAt()
                        ? new DateTimeImmutable($employee->getCreatedAt()->format('Y-m-d'))
                        : $today;

                    $balance = new LeaveBalance();
                    $balance->setEmployee($employee)
                        ->setEmployeeName($employee->getFullName())
                        ->setAvailableDays(0)
                        ->setTotalAccrued(0)
                        ->setTotalUsed(0)
                        ->setHireDate($hireDate);

                    $this->em->persist($balance);
                    $balanceByEmployeeId[$employeeId] = $balance;
                    $needsFlush = true;
                }

                $balance = $balanceByEmployeeId[$employeeId];

                // Compute accrual in-memory (no extra DB query per employee)
                $referenceDate = $balance->getLastAccrualDate()
                    ? new DateTimeImmutable($balance->getLastAccrualDate()->format('Y-m-d'))
                    : ($balance->getHireDate()
                        ? new DateTimeImmutable($balance->getHireDate()->format('Y-m-d'))
                        : $today);

                $months = $this->getCompletedMonths($referenceDate, $today);
                if ($months > 0) {
                    $accruedDays = round(self::MONTHLY_ACCRUAL_DAYS * $months, 2);
                    $balance->setEmployeeName($employee->getFullName());
                    $balance->setAvailableDays($balance->getAvailableDays() + $accruedDays);
                    $balance->setTotalAccrued($balance->getTotalAccrued() + $accruedDays);
                    $balance->setLastAccrualDate($today);
                    $needsFlush = true;
                }
            }

            // Single flush for all changes
            if ($needsFlush) {
                $this->em->flush();
            }

            return array_values($balanceByEmployeeId);
        } catch (\Throwable $e) {
            $this->logger->error('getBalancesByRh failed', ['rhId' => $rhId, 'exception' => $e]);
            return [];
        }
    }

    /** @return array{available_sum: float, used_sum: float, accrued_sum: float, employees_count: int} */
    public function getCreditSummaryByRh(int $rhId): array
    {
        try {
            return $this->leaveBalanceRepository->getCreditSummaryByRh($rhId);
        } catch (\Throwable) {
            return ['available_sum' => 0.0, 'used_sum' => 0.0, 'accrued_sum' => 0.0, 'employees_count' => 0];
        }
    }

    /** @return array{success: bool, message: string} */
    public function grantManualCreditByRh(int $rhId, int $employeeId, float $creditDays): array
    {
        if ($creditDays <= 0) {
            return ['success' => false, 'message' => 'Le credit doit etre superieur a 0 jour.'];
        }

        try {
            $employee = $this->employeeRepository->find($employeeId);

            if (!$employee || $employee->getRhId() !== $rhId) {
                return ['success' => false, 'message' => 'Employe introuvable dans votre equipe.'];
            }

            $this->accrueIfNeeded($employeeId);

            $balance = $this->leaveBalanceRepository->findByEmployee($employeeId);
            if (!$balance) {
                return ['success' => false, 'message' => 'Solde conge introuvable pour cet employe.'];
            }

            $creditDays = round($creditDays, 2);

            $balance->setAvailableDays($balance->getAvailableDays() + $creditDays);
            $balance->setTotalAccrued($balance->getTotalAccrued() + $creditDays);

            $this->em->flush();

            return [
                'success' => true,
                'message' => sprintf('%.2f jour(s) ajoute(s) au solde de %s.', $creditDays, $employee->getFullName()),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('grantManualCreditByRh failed', [
                'rhId' => $rhId,
                'employeeId' => $employeeId,
                'creditDays' => $creditDays,
                'exception' => $e,
            ]);
            return ['success' => false, 'message' => 'Impossible d\'ajouter le credit pour le moment.'];
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
