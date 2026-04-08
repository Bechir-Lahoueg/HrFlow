<?php

namespace App\Repository;

use App\Entity\Prime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Prime>
 */
class PrimeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prime::class);
    }

    /** @return Prime[] */
    public function findByEmployee(int $employeeId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.employee = :employeeId')
            ->setParameter('employeeId', $employeeId)
            ->orderBy('p.dateAttribution', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Prime[] */
    public function findByEmployeeAndPeriod(int $employeeId, int $mois, int $annee): array
    {
        // Calculate date range for the given month and year
        $startDate = new \DateTime("$annee-$mois-01");
        $endDate = new \DateTime("$annee-$mois-01");
        $endDate->modify('last day of this month')->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->where('p.employee = :employeeId')
            ->andWhere('p.dateAttribution >= :startDate')
            ->andWhere('p.dateAttribution <= :endDate')
            ->setParameter('employeeId', $employeeId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('p.dateAttribution', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Prime[] */
    public function findByRhAndSearch(int $rhId, string $employeeSearch = '', string $typeSearch = '', string $sort = 'dateAttribution-DESC'): array
    {
        $sortParts = explode('-', $sort);
        $sortField = $sortParts[0] ?? 'dateAttribution';
        $sortDir = $sortParts[1] ?? 'DESC';

        $qb = $this->createQueryBuilder('p')
            ->join('p.employee', 'e')
            ->where('e.rhId = :rhId')
            ->setParameter('rhId', $rhId);

        if (trim($employeeSearch) !== '') {
            $qb->andWhere('CONCAT(e.firstName, \' \', e.lastName) LIKE :empSearch')
               ->setParameter('empSearch', '%' . trim($employeeSearch) . '%');
        }

        if (trim($typeSearch) !== '') {
            $qb->andWhere('p.typePrime LIKE :typeSearch')
               ->setParameter('typeSearch', '%' . trim($typeSearch) . '%');
        }

        if ($sortField === 'employee') {
            $qb->orderBy('e.firstName', $sortDir)->addOrderBy('e.lastName', $sortDir);
        } else {
            $qb->orderBy('p.' . $sortField, $sortDir);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return Prime[] */
    public function findByRh(int $rhId): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.employee', 'e')
            ->where('e.rhId = :rhId')
            ->setParameter('rhId', $rhId)
            ->orderBy('p.dateAttribution', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getTotalByEmployeeAndPeriod(int $employeeId, int $mois, int $annee): string
    {
        // Calculate date range for the given month and year
        $startDate = new \DateTime("$annee-$mois-01");
        $endDate = new \DateTime("$annee-$mois-01");
        $endDate->modify('last day of this month')->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.montant)')
            ->where('p.employee = :employeeId')
            ->andWhere('p.dateAttribution >= :startDate')
            ->andWhere('p.dateAttribution <= :endDate')
            ->setParameter('employeeId', $employeeId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return (string) ($result ?? '0.00');
    }

    public function getStatsByRh(int $rhId): array
    {
        $query = $this->createQueryBuilder('p')
            ->select(
                'COUNT(p.id) as totalPrimes',
                'SUM(p.montant) as totalMontant'
            )
            ->join('p.employee', 'e')
            ->where('e.rhId = :rhId')
            ->setParameter('rhId', $rhId)
            ->getQuery()
            ->getOneOrNullResult();

        return $query ?? [
            'totalPrimes' => 0,
            'totalMontant' => '0.00',
        ];
    }

    public function countByRh(int $rhId): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->join('p.employee', 'e')
            ->where('e.rhId = :rhId')
            ->setParameter('rhId', $rhId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
