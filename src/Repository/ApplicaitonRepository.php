<?php

namespace App\Repository;

use App\Entity\Applicaiton;
use App\Entity\Candidate;
use App\Entity\JobOffer;
use App\Security\DbUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Applicaiton>
 */
class ApplicaitonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Applicaiton::class);
    }

    /**
     * Find applications by candidate
     * @return Applicaiton[]
     */
    public function findByCandidate(Candidate $candidate): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.jobOffer', 'jo')
            ->addSelect('jo')
            ->where('a.candidate = :candidate')
            ->andWhere('a.isDeleted = false')
            ->setParameter('candidate', $candidate)
            ->orderBy('a.appliedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count applications by candidate and status
     */
    public function countByCandidateAndStatus(Candidate $candidate, string $status): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.candidate = :candidate')
            ->andWhere('a.status = :status')
            ->andWhere('a.isDeleted = false')
            ->setParameter('candidate', $candidate)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count all applications by candidate
     */
    public function countAllByCandidate(Candidate $candidate): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.candidate = :candidate')
            ->andWhere('a.isDeleted = false')
            ->setParameter('candidate', $candidate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Check if candidate has already applied to a job offer
     */
    public function hasCandidateApplied(Candidate $candidate, int $jobOfferId): bool
    {
        $count = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.candidate = :candidate')
            ->andWhere('a.jobOffer = :jobOfferId')
            ->andWhere('a.isDeleted = false')
            ->setParameter('candidate', $candidate)
            ->setParameter('jobOfferId', $jobOfferId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Find applications by RH with optional filters
     * @return Applicaiton[]
     */
    public function findByRh(
        DbUser $rh,
        ?int $jobOfferId = null,
        ?string $status = null,
        ?string $department = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->join('a.jobOffer', 'jo')
            ->where('jo.createdBy = :rhId')
            ->andWhere('a.isDeleted = false')
            ->setParameter('rhId', $rh->getId())
            ->orderBy('a.appliedAt', 'DESC');

        if ($jobOfferId) {
            $qb->andWhere('a.jobOffer = :jobOfferId')
               ->setParameter('jobOfferId', $jobOfferId);
        }

        if ($status) {
            $qb->andWhere('a.status = :status')
               ->setParameter('status', $status);
        }

        if ($department) {
            $qb->andWhere('a.department LIKE :department')
               ->setParameter('department', '%' . $department . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find one application by RH (ownership verification)
     */
    public function findOneByRh(int $id, DbUser $rh): ?Applicaiton
    {
        return $this->createQueryBuilder('a')
            ->join('a.jobOffer', 'jo')
            ->where('a.id = :id')
            ->andWhere('jo.createdBy = :rhId')
            ->andWhere('a.isDeleted = false')
            ->setParameter('id', $id)
            ->setParameter('rhId', $rh->getId())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find applications by job offer (owned by RH)
     * @return Applicaiton[]
     */
    public function findByJobOffer(int $jobOfferId, DbUser $rh): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.jobOffer', 'jo')
            ->where('a.jobOffer = :jobOfferId')
            ->andWhere('jo.createdBy = :rhId')
            ->andWhere('a.isDeleted = false')
            ->setParameter('jobOfferId', $jobOfferId)
            ->setParameter('rhId', $rh->getId())
            ->orderBy('a.appliedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all job offers for filter dropdown (owned by RH)
     * @return JobOffer[]
     */
    public function findJobOffersForFilter(DbUser $rh): array
    {
        return $this->getEntityManager()->createQuery(
            'SELECT jo FROM App\Entity\JobOffer jo 
             WHERE jo.createdBy = :rhId 
             AND jo.isDeleted = false 
             ORDER BY jo.title'
        )
        ->setParameter('rhId', $rh->getId())
        ->getResult();
    }

    /**
     * Get application statistics by status
     * @return array<string, int>
     */
    public function getStatusStats(DbUser $rh): array
    {
        $results = $this->getEntityManager()->createQuery(
            'SELECT a.status, COUNT(a.id) as count
             FROM App\Entity\Applicaiton a
             JOIN a.jobOffer jo
             WHERE jo.createdBy = :rhId
             AND a.isDeleted = false
             GROUP BY a.status'
        )
        ->setParameter('rhId', $rh->getId())
        ->getResult();

        $stats = ['PENDING' => 0, 'REVIEWING' => 0, 'INTERVIEW' => 0, 'OFFER' => 0, 'HIRED' => 0, 'REJECTED' => 0];
        foreach ($results as $row) {
            $stats[$row['status']] = (int) $row['count'];
        }
        return $stats;
    }

    /**
     * Count total applications for an RH
     */
    public function countByRh(DbUser $rh): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->join('a.jobOffer', 'jo')
            ->where('jo.createdBy = :rhId')
            ->andWhere('a.isDeleted = false')
            ->setParameter('rhId', $rh->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count pending applications for an RH
     */
    public function countPending(DbUser $rh): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->join('a.jobOffer', 'jo')
            ->where('jo.createdBy = :rhId')
            ->andWhere('a.status = :pending')
            ->andWhere('a.isDeleted = false')
            ->setParameter('rhId', $rh->getId())
            ->setParameter('pending', 'PENDING')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find deleted applications by RH
     * @return Applicaiton[]
     */
    public function findDeletedByRh(DbUser $rh): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.jobOffer', 'jo')
            ->where('jo.createdBy = :rhId')
            ->andWhere('a.isDeleted = true')
            ->setParameter('rhId', $rh->getId())
            ->orderBy('a.appliedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find one application by RH including deleted (for restore/permanent delete)
     */
    public function findOneByRhIncludingDeleted(int $id, DbUser $rh): ?Applicaiton
    {
        return $this->createQueryBuilder('a')
            ->join('a.jobOffer', 'jo')
            ->where('a.id = :id')
            ->andWhere('jo.createdBy = :rhId')
            ->setParameter('id', $id)
            ->setParameter('rhId', $rh->getId())
            ->getQuery()
            ->getOneOrNullResult();
    }
}
