<?php

namespace App\Repository\Rh;

use App\Entity\Rh\Employee;
use App\Security\DbUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Employee>
 */
class EmployeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employee::class);
    }

    /**
     * @return Employee[] Returns all employees managed by an RH
     */
    public function findByRh(DbUser $rh): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.rhId = :rhId')
            ->setParameter('rhId', $rh->getId())
            ->orderBy('e.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByRh(int $id, DbUser $rh): ?Employee
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.id = :id')
            ->andWhere('e.rhId = :rhId')
            ->setParameter('id', $id)
            ->setParameter('rhId', $rh->getId())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count all employees for a given RH (SQL COUNT — no hydration).
     */
    public function countByRh(int $rhId): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.rhId = :rhId')
            ->setParameter('rhId', $rhId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Search employees by name, email or job title, scoped to RH.
     *
     * @return Employee[]
     */
    public function searchByName(int $rhId, string $query, int $limit = 10): array
    {
        $q = '%' . trim($query) . '%';
        return $this->createQueryBuilder('e')
            ->where('e.rhId = :rhId')
            ->andWhere(
                'CONCAT(e.firstName, \' \', e.lastName) LIKE :q'
                . ' OR e.email LIKE :q'
                . ' OR e.jobTitle LIKE :q'
            )
            ->setParameter('rhId', $rhId)
            ->setParameter('q', $q)
            ->orderBy('e.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count employees for a given RH with high deduction count in recent months.
     *
     * @return array<array{employee: Employee, deductionCount: int}>
     */
    public function findWithHighDeductions(int $rhId, int $minDeductions = 3, int $recentMonths = 3): array
    {
        $since = new \DateTime();
        $since->modify("-{$recentMonths} months");

        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT e.id, COUNT(d.id) as deduction_count
            FROM employees e
            JOIN deductions d ON d.employee_id = e.id
            WHERE e.rh_id = :rhId
              AND d.date_deduction >= :since
            GROUP BY e.id
            HAVING COUNT(d.id) >= :minDeductions
            ORDER BY deduction_count DESC
        ';

        $rows = $conn->executeQuery($sql, [
            'rhId' => $rhId,
            'since' => $since->format('Y-m-d'),
            'minDeductions' => $minDeductions,
        ])->fetchAllAssociative();

        if (empty($rows)) {
            return [];
        }

        // Batch-load all employees in ONE query instead of N individual finds
        $ids = array_column($rows, 'id');
        $deductionCounts = array_column($rows, 'deduction_count', 'id');

        $employees = $this->createQueryBuilder('e')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $employeeMap = [];
        foreach ($employees as $employee) {
            $employeeMap[$employee->getId()] = $employee;
        }

        // Preserve ORDER BY deduction_count DESC from original SQL
        $results = [];
        foreach ($rows as $row) {
            $id = $row['id'];
            if (isset($employeeMap[$id])) {
                $results[] = [
                    'employee' => $employeeMap[$id],
                    'deductionCount' => (int) $row['deduction_count'],
                ];
            }
        }

        return $results;
    }
}
