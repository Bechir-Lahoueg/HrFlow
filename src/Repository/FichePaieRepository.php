<?php

namespace App\Repository;

use App\Entity\FichePaie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FichePaie>
 */
class FichePaieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FichePaie::class);
    }

    /** @return FichePaie[] */
    public function findByEmployeeAndPeriod(int $employeeId, int $mois, int $annee): array
    {
        return $this->createQueryBuilder('fp')
            ->where('fp.employee = :employeeId')
            ->andWhere('fp.mois = :mois')
            ->andWhere('fp.annee = :annee')
            ->setParameter('employeeId', $employeeId)
            ->setParameter('mois', $mois)
            ->setParameter('annee', $annee)
            ->getQuery()
            ->getResult();
    }

    public function findByEmployeeAndPeriodSingle(int $employeeId, int $mois, int $annee): ?FichePaie
    {
        return $this->createQueryBuilder('fp')
            ->where('fp.employee = :employeeId')
            ->andWhere('fp.mois = :mois')
            ->andWhere('fp.annee = :annee')
            ->setParameter('employeeId', $employeeId)
            ->setParameter('mois', $mois)
            ->setParameter('annee', $annee)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return FichePaie[] */
    public function findByRhAndSearch(int $rhId, string $employeeSearch = '', string $periodSearch = '', string $sort = 'createdAt-DESC'): array
    {
        $sortParts = explode('-', $sort);
        $sortField = $sortParts[0] ?? 'createdAt';
        $sortDir = $sortParts[1] ?? 'DESC';

        $qb = $this->createQueryBuilder('fp')
            ->join('fp.employee', 'e')
            ->where('e.rhId = :rhId')
            ->setParameter('rhId', $rhId);

        if (trim($employeeSearch) !== '') {
            $qb->andWhere('CONCAT(e.firstName, \' \', e.lastName) LIKE :empSearch')
               ->setParameter('empSearch', '%' . trim($employeeSearch) . '%');
        }

        if (trim($periodSearch) !== '') {
            $qb->andWhere('CONCAT(fp.mois, \'-\', fp.annee) LIKE :periodSearch')
               ->setParameter('periodSearch', '%' . trim($periodSearch) . '%');
        }

        if ($sortField === 'employee') {
            $qb->orderBy('e.firstName', $sortDir)->addOrderBy('e.lastName', $sortDir);
        } elseif ($sortField === 'createdAt' || $sortField === 'updatedAt') {
            $qb->orderBy('fp.' . $sortField, $sortDir);
        } else {
            // Default to assuming it's a FichePaie property
            $qb->orderBy('fp.' . $sortField, $sortDir);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return FichePaie[] */
    public function findByRh(int $rhId): array
    {
        return $this->createQueryBuilder('fp')
            ->join('fp.employee', 'e')
            ->where('e.rhId = :rhId')
            ->setParameter('rhId', $rhId)
            ->orderBy('fp.createdAt', 'DESC')
            ->addOrderBy('fp.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getStatsByRh(int $rhId): array
    {
        $query = $this->createQueryBuilder('fp')
            ->select(
                'COUNT(fp.id) as totalFiches',
                'SUM(fp.salaireBrut) as totalBrut',
                'SUM(fp.totalPrimes) as totalPrimes',
                'SUM(fp.totalDeductions) as totalDeductions',
                'SUM(fp.salaireNet) as totalNet',
                'AVG(fp.salaireNet) as avgSalaire'
            )
            ->join('fp.employee', 'e')
            ->where('e.rhId = :rhId')
            ->setParameter('rhId', $rhId)
            ->getQuery()
            ->getOneOrNullResult();

        return $query ?? [
            'totalFiches' => 0,
            'totalBrut' => '0.00',
            'totalPrimes' => '0.00',
            'totalDeductions' => '0.00',
            'totalNet' => '0.00',
            'avgSalaire' => '0.00',
        ];
    }

    public function countByRh(int $rhId): int
    {
        return (int) $this->createQueryBuilder('fp')
            ->select('COUNT(fp.id)')
            ->join('fp.employee', 'e')
            ->where('e.rhId = :rhId')
            ->setParameter('rhId', $rhId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
