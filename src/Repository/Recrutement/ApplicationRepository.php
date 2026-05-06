<?php

namespace App\Repository\Recrutement;

use App\Entity\Recrutement\Application;
use App\Entity\Recrutement\Candidate;
use App\Entity\Recrutement\JobOffer;
use App\Security\DbUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Application>
 */
class ApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Application::class);
    }

    /**
     * Find applications by candidate
     * @return Application[]
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
     * @return Application[]
     */
    public function findByRh(
        DbUser $rh,
        ?int $jobOfferId = null,
        ?string $status = null,
        ?string $department = null,
        ?string $search = null
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

        if ($search) {
            $qb->andWhere('a.candidateName LIKE :search OR a.emailAddress LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find applications by RH with optional filters (returns Query for pagination)
     * @return \Doctrine\ORM\Query<int, Application>
     */
    public function findByRhQuery(
        DbUser $rh,
        ?int $jobOfferId = null,
        ?string $status = null,
        ?string $department = null,
        ?string $search = null
    ): \Doctrine\ORM\Query {
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

        if ($search) {
            $qb->andWhere('a.candidateName LIKE :search OR a.emailAddress LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery();
    }

    /**
     * Find one application by RH (ownership verification)
     */
    public function findOneByRh(int $id, DbUser $rh): ?Application
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
     * @return Application[]
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
            'SELECT jo FROM App\Entity\Recrutement\JobOffer jo 
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
    public function getStatusStats(DbUser $rh, ?JobOffer $job = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a.status, COUNT(a.id) as count')
            ->join('a.jobOffer', 'jo')
            ->where('jo.createdBy = :rhId')
            ->andWhere('a.isDeleted = false')
            ->setParameter('rhId', $rh->getId())
            ->groupBy('a.status');

        if ($job) {
            $qb->andWhere('a.jobOffer = :job')
               ->setParameter('job', $job);
        }

        $results = $qb->getQuery()->getResult();

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
     * @return Application[]
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
    public function findOneByRhIncludingDeleted(int $id, DbUser $rh): ?Application
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

    /**
     * Global response rate: non-pending applications / total applications.
     */
    public function getGlobalResponseRate(): int
    {
        $result = $this->createQueryBuilder('a')
            ->select('COUNT(a.id) AS total')
            ->addSelect("SUM(CASE WHEN a.status <> 'PENDING' THEN 1 ELSE 0 END) AS responded")
            ->where('a.isDeleted = false')
            ->getQuery()
            ->getSingleResult();

        $total = (int) ($result['total'] ?? 0);
        $responded = (int) ($result['responded'] ?? 0);

        if ($total === 0) {
            return 0;
        }

        return (int) round(($responded / $total) * 100);
    }
}
