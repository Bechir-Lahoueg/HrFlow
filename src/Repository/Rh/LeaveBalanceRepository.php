<?php

namespace App\Repository\Rh;

use App\Entity\Rh\LeaveBalance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LeaveBalance>
 */
class LeaveBalanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeaveBalance::class);
    }

    public function findByEmployee(int $employeeId): ?LeaveBalance
    {
        return $this->createQueryBuilder('lb')
            ->where('lb.employee = :employeeId')
            ->setParameter('employeeId', $employeeId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return LeaveBalance[] */
    public function findByRh(int $rhId): array
    {
        return $this->createQueryBuilder('lb')
            ->join('lb.employee', 'e')
            ->addSelect('e')
            ->where('e.rhId = :rhId')
            ->setParameter('rhId', $rhId)
            ->orderBy('e.firstName', 'ASC')
            ->addOrderBy('e.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return array{available_sum: float, used_sum: float, accrued_sum: float, employees_count: int} */
    public function getCreditSummaryByRh(int $rhId): array
    {
        $result = $this->createQueryBuilder('lb')
            ->select(
                'COALESCE(SUM(lb.availableDays), 0) AS available_sum',
                'COALESCE(SUM(lb.totalUsed), 0) AS used_sum',
                'COALESCE(SUM(lb.totalAccrued), 0) AS accrued_sum',
                'COUNT(DISTINCT lb.employee) AS employees_count'
            )
            ->join('lb.employee', 'e')
            ->where('e.rhId = :rhId')
            ->setParameter('rhId', $rhId)
            ->getQuery()
            ->getSingleResult();

        return [
            'available_sum' => (float) ($result['available_sum'] ?? 0),
            'used_sum' => (float) ($result['used_sum'] ?? 0),
            'accrued_sum' => (float) ($result['accrued_sum'] ?? 0),
            'employees_count' => (int) ($result['employees_count'] ?? 0),
        ];
    }
}
