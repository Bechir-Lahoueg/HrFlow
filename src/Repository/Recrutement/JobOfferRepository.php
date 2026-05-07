<?php

namespace App\Repository\Recrutement;

use App\Entity\Recrutement\JobOffer;
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

        return $qb->getQuery()
            ->setResultCacheLifetime(300) // 5 min — job offer list rarely changes
            ->setResultCacheId('job_offers_rh_' . $rh->getId() . '_' . ($status ?? 'all'))
            ->getResult();
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
             FROM App\Entity\Recrutement\Application a
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
             FROM App\Entity\Recrutement\JobOffer jo
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

    /**
     * Find deleted job offers by RH
     * @return JobOffer[]
     */
    public function findDeletedByRh(DbUser $rh): array
    {
        return $this->createQueryBuilder('jo')
            ->where('jo.createdBy = :rhId')
            ->andWhere('jo.isDeleted = true')
            ->setParameter('rhId', $rh->getId())
            ->orderBy('jo.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find one job offer by RH including deleted (for restore/permanent delete)
     */
    public function findOneByRhIncludingDeleted(int $id, DbUser $rh): ?JobOffer
    {
        return $this->createQueryBuilder('jo')
            ->where('jo.id = :id')
            ->andWhere('jo.createdBy = :rhId')
            ->setParameter('id', $id)
            ->setParameter('rhId', $rh->getId())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find published job offers for public page (limited preview)
     * @return JobOffer[]
     */
    public function findPublished(int $limit = 6): array
    {
        return $this->createQueryBuilder('jo')
            ->where('jo.status = :status')
            ->andWhere('jo.isDeleted = false')
            ->setParameter('status', 'OPEN')
            ->orderBy('jo.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count public OPEN job offers (not deleted).
     */
    public function countPublicOpenOffers(): int
    {
        return (int) $this->createQueryBuilder('jo')
            ->select('COUNT(jo.id)')
            ->where('jo.status = :status')
            ->andWhere('jo.isDeleted = false')
            ->setParameter('status', 'OPEN')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Search and filter job offers for candidates
     * @return JobOffer[]
     */
    public function searchForCandidates(?string $search = null, ?string $department = null, ?string $location = null, ?string $employmentType = null): array
    {
        $qb = $this->createQueryBuilder('jo')
            ->where('jo.status = :status')
            ->andWhere('jo.isDeleted = false')
            ->setParameter('status', 'OPEN')
            ->orderBy('jo.createdAt', 'DESC');

        if ($search) {
            $qb->andWhere('(jo.title LIKE :search OR jo.description LIKE :search)')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($department) {
            $qb->andWhere('jo.department = :department')
               ->setParameter('department', $department);
        }

        if ($location) {
            $qb->andWhere('jo.location = :location')
               ->setParameter('location', $location);
        }

        if ($employmentType) {
            $qb->andWhere('jo.employmentType = :employmentType')
               ->setParameter('employmentType', $employmentType);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get distinct departments for filter dropdown
     * @return string[]
     */
    public function getDistinctDepartments(): array
    {
        $results = $this->createQueryBuilder('jo')
            ->select('DISTINCT jo.department')
            ->where('jo.status = :status')
            ->andWhere('jo.isDeleted = false')
            ->andWhere('jo.department IS NOT NULL')
            ->setParameter('status', 'OPEN')
            ->orderBy('jo.department', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'department');
    }

    /**
     * Get distinct locations for filter dropdown
     * @return string[]
     */
    public function getDistinctLocations(): array
    {
        $results = $this->createQueryBuilder('jo')
            ->select('DISTINCT jo.location')
            ->where('jo.status = :status')
            ->andWhere('jo.isDeleted = false')
            ->andWhere('jo.location IS NOT NULL')
            ->setParameter('status', 'OPEN')
            ->orderBy('jo.location', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'location');
    }

    /**
     * Get distinct employment types for filter dropdown
     * @return string[]
     */
    public function getDistinctEmploymentTypes(): array
    {
        $results = $this->createQueryBuilder('jo')
            ->select('DISTINCT jo.employmentType')
            ->where('jo.status = :status')
            ->andWhere('jo.isDeleted = false')
            ->andWhere('jo.employmentType IS NOT NULL')
            ->setParameter('status', 'OPEN')
            ->orderBy('jo.employmentType', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'employmentType');
    }
}
