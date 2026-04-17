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
     * Search employees by name (first or last) for autocomplete, scoped to RH.
     *
     * @return Employee[]
     */
    public function searchByName(int $rhId, string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.rhId = :rhId')
            ->andWhere('CONCAT(e.firstName, \' \', e.lastName) LIKE :q')
            ->setParameter('rhId', $rhId)
            ->setParameter('q', '%' . trim($query) . '%')
            ->orderBy('e.lastName', 'ASC')
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

        $results = [];
        foreach ($rows as $row) {
            $employee = $this->find($row['id']);
            if ($employee) {
                $results[] = [
                    'employee' => $employee,
                    'deductionCount' => (int) $row['deduction_count'],
                ];
            }
        }

        return $results;
    }
}
