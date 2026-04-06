<?php

namespace App\Repository;

use App\Entity\JobOffer;
use App\Security\DbUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JobOffer>
 */
class JobOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobOffer::class);
    }

    /**
     * Find job offers by RH with optional status filter (returns entities only)
     * @return JobOffer[]
     */
    public function findByRh(DbUser $rh, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('jo')
            ->where('jo.createdBy = :rhId')
            ->andWhere('jo.isDeleted = false')
            ->setParameter('rhId', $rh->getId())
            ->orderBy('jo.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('jo.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find job offers with application counts for stats display
     * @return array<int, array{0: JobOffer, applications_count: string}>
     */
    public function findByRhWithApplicationCounts(DbUser $rh, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('jo')
            ->leftJoin('jo.applications', 'a', 'WITH', 'a.isDeleted = false')
            ->addSelect('COUNT(a.id) as applications_count')
            ->where('jo.createdBy = :rhId')
            ->andWhere('jo.isDeleted = false')
            ->setParameter('rhId', $rh->getId())
            ->groupBy('jo.id')
            ->orderBy('jo.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('jo.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find one job offer by RH (for edit/delete verification)
     */
    public function findOneByRh(int $id, DbUser $rh): ?JobOffer
    {
        return $this->createQueryBuilder('jo')
            ->where('jo.id = :id')
            ->andWhere('jo.createdBy = :rhId')
            ->andWhere('jo.isDeleted = false')
            ->setParameter('id', $id)
            ->setParameter('rhId', $rh->getId())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count total applications for all job offers of an RH
     */
    public function countTotalApplications(DbUser $rh): int
    {
        return (int) $this->getEntityManager()->createQuery(
            'SELECT COUNT(a.id) 
             FROM App\Entity\Applicaiton a
             JOIN a.jobOffer jo
             WHERE jo.createdBy = :rhId
             AND a.isDeleted = false'
        )
        ->setParameter('rhId', $rh->getId())
        ->getSingleScalarResult();
    }

    /**
     * Get job offer statistics by status
     * @return array<string, int>
     */
    public function getStatusStats(DbUser $rh): array
    {
        $results = $this->getEntityManager()->createQuery(
            'SELECT jo.status, COUNT(jo.id) as count
             FROM App\Entity\JobOffer jo
             WHERE jo.createdBy = :rhId
             AND jo.isDeleted = false
             GROUP BY jo.status'
        )
        ->setParameter('rhId', $rh->getId())
        ->getResult();

        $stats = ['DRAFT' => 0, 'PUBLISHED' => 0, 'CLOSED' => 0, 'ARCHIVED' => 0];
        foreach ($results as $row) {
            $stats[$row['status']] = (int) $row['count'];
        }
        return $stats;
    }

    /**
     * Count total job offers for an RH
     */
    public function countByRh(DbUser $rh): int
    {
        return (int) $this->createQueryBuilder('jo')
            ->select('COUNT(jo.id)')
            ->where('jo.createdBy = :rhId')
            ->andWhere('jo.isDeleted = false')
            ->setParameter('rhId', $rh->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
