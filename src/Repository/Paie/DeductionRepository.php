<?php

namespace App\Repository\Paie;

use App\Entity\Paie\Deduction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Deduction>
 */
class DeductionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deduction::class);
    }

    /** @return Deduction[] */
    public function findByEmployee(int $employeeId): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.employee = :employeeId')
            ->setParameter('employeeId', $employeeId)
            ->orderBy('d.dateDeduction', 'DESC')
            ->addOrderBy('d.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Deduction[] */
    public function findByEmployeeAndPeriod(int $employeeId, int $mois, int $annee): array
    {
        // Calculate date range for the given month and year
        $dateStr = sprintf('%d-%02d-01', $annee, $mois);
        $startDate = new \DateTime($dateStr);
        $endDate = new \DateTime($dateStr);
        $endDate->modify('last day of this month')->setTime(23, 59, 59);

        return $this->createQueryBuilder('d')
            ->where('d.employee = :employeeId')
            ->andWhere('d.dateDeduction >= :startDate')
            ->andWhere('d.dateDeduction <= :endDate')
            ->setParameter('employeeId', $employeeId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('d.dateDeduction', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Deduction[] */
    public function findByRhAndSearch(int $rhId, string $employeeSearch = '', string $typeSearch = '', string $sort = 'dateDeduction-DESC'): array
    {
        $sortParts = explode('-', $sort);
        $sortField = $sortParts[0] ?? 'dateDeduction';
        $sortDir = $sortParts[1] ?? 'DESC';

        $qb = $this->createQueryBuilder('d')
            ->join('d.employee', 'e')
            ->where('e.rhId = :rhId')
            ->setParameter('rhId', $rhId);

        if (trim($employeeSearch) !== '') {
            $qb->andWhere('CONCAT(e.firstName, \' \', e.lastName) LIKE :empSearch')
               ->setParameter('empSearch', '%' . trim($employeeSearch) . '%');
        }

        if (trim($typeSearch) !== '') {
            $qb->andWhere('d.typeDeduction LIKE :typeSearch')
               ->setParameter('typeSearch', '%' . trim($typeSearch) . '%');
        }

        if ($sortField === 'employee') {
            $qb->orderBy('e.firstName', $sortDir)->addOrderBy('e.lastName', $sortDir);
        } else {
            $qb->orderBy('d.' . $sortField, $sortDir);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return Deduction[] */
    public function findByRh(int $rhId): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.employee', 'e')
            ->where('e.rhId = :rhId')
            ->setParameter('rhId', $rhId)
            ->orderBy('d.dateDeduction', 'DESC')
            ->addOrderBy('d.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getTotalByEmployeeAndPeriod(int $employeeId, int $mois, int $annee): string
    {
        // Calculate date range for the given month and year
        $dateStr = sprintf('%d-%02d-01', $annee, $mois);
        $startDate = new \DateTime($dateStr);
        $endDate = new \DateTime($dateStr);
        $endDate->modify('last day of this month')->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('d')
            ->select('SUM(d.montant)')
            ->where('d.employee = :employeeId')
            ->andWhere('d.dateDeduction >= :startDate')
            ->andWhere('d.dateDeduction <= :endDate')
            ->setParameter('employeeId', $employeeId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return (string) ($result ?? '0.00');
    }

    /** @return array<string, mixed> */
    public function getStatsByRh(int $rhId): array
    {
        $query = $this->createQueryBuilder('d')
            ->select(
                'COUNT(d.id) as totalDeductions',
                'SUM(d.montant) as totalMontant'
            )
            ->join('d.employee', 'e')
            ->where('e.rhId = :rhId')
            ->setParameter('rhId', $rhId)
            ->getQuery()
            ->getOneOrNullResult();

        return $query ?? [
            'totalDeductions' => 0,
            'totalMontant' => '0.00',
        ];
    }

    public function countByRh(int $rhId): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->join('d.employee', 'e')
            ->where('e.rhId = :rhId')
            ->setParameter('rhId', $rhId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
