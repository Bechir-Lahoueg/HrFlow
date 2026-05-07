<?php

namespace App\Repository\Recrutement;

use App\Entity\Recrutement\Application;
use App\Entity\Recrutement\Interview;
use App\Security\DbUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Interview>
 */
class InterviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Interview::class);
    }

    /**
     * Find interviews by RH with optional application filter
     * @return Interview[]
     */
    public function findByRh(DbUser $rh, ?int $applicationId = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->join('i.application', 'a')
            ->join('a.jobOffer', 'jo')
            ->addSelect('a', 'jo')
            ->where('jo.createdBy = :rhId')
            ->andWhere('i.isDeleted = false')
            ->setParameter('rhId', $rh->getId())
            ->orderBy('i.interviewDate', 'DESC');

        if ($applicationId) {
            $qb->andWhere('i.application = :applicationId')
               ->setParameter('applicationId', $applicationId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find one interview by RH (ownership verification)
     */
    public function findOneByRh(int $id, DbUser $rh): ?Interview
    {
        return $this->createQueryBuilder('i')
            ->join('i.application', 'a')
            ->join('a.jobOffer', 'jo')
            ->addSelect('a', 'jo')
            ->where('i.id = :id')
            ->andWhere('jo.createdBy = :rhId')
            ->andWhere('i.isDeleted = false')
            ->setParameter('id', $id)
            ->setParameter('rhId', $rh->getId())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get all applications for dropdown (owned by RH)
     * @return Application[]
     */
    public function findApplicationsForDropdown(DbUser $rh): array
    {
        return $this->getEntityManager()->createQuery(
            'SELECT a, jo FROM App\Entity\Recrutement\Application a
             JOIN a.jobOffer jo
             WHERE jo.createdBy = :rhId
             AND a.isDeleted = false
             ORDER BY a.candidateName'
        )
        ->setParameter('rhId', $rh->getId())
        ->getResult();
    }

    /**
     * Find interviews by application (owned by RH)
     * @return Interview[]
     */
    public function findByApplication(int $applicationId): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.application = :applicationId')
            ->setParameter('applicationId', $applicationId)
            ->orderBy('i.interviewDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total interviews for an RH
     */
    public function countByRh(DbUser $rh): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->join('i.application', 'a')
            ->join('a.jobOffer', 'jo')
            ->where('jo.createdBy = :rhId')
            ->andWhere('i.isDeleted = false')
            ->setParameter('rhId', $rh->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count upcoming interviews for an RH
     */
    public function countUpcoming(DbUser $rh): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->join('i.application', 'a')
            ->join('a.jobOffer', 'jo')
            ->where('jo.createdBy = :rhId')
            ->andWhere('i.isDeleted = false')
            ->andWhere('i.interviewDate >= :now')
            ->setParameter('rhId', $rh->getId())
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get interview statistics by result
     * @return array<string, int>
     */
    public function getResultStats(DbUser $rh): array
    {
        $results = $this->getEntityManager()->createQuery(
            'SELECT i.result, COUNT(i.id) as count
             FROM App\Entity\Recrutement\Interview i
             JOIN i.application a
             JOIN a.jobOffer jo
             WHERE jo.createdBy = :rhId
             AND i.isDeleted = false
             GROUP BY i.result'
        )
        ->setParameter('rhId', $rh->getId())
        ->getResult();

        $stats = ['PENDING' => 0, 'PASSED' => 0, 'FAILED' => 0, 'NO_SHOW' => 0];
        foreach ($results as $row) {
            $stats[$row['result']] = (int) $row['count'];
        }
        return $stats;
    }

    /**
     * Calculate average score for completed interviews
     */
    public function getAverageScore(DbUser $rh): ?float
    {
        $result = $this->getEntityManager()->createQuery(
            'SELECT AVG(i.score) as avg_score
             FROM App\Entity\Recrutement\Interview i
             JOIN i.application a
             JOIN a.jobOffer jo
             WHERE jo.createdBy = :rhId
             AND i.isDeleted = false
             AND i.score > 0'
        )
        ->setParameter('rhId', $rh->getId())
        ->getSingleScalarResult();

        return $result ? (float) $result : null;
    }

    /**
     * Find deleted interviews by RH
     * @return Interview[]
     */
    public function findDeletedByRh(DbUser $rh): array
    {
        return $this->createQueryBuilder('i')
            ->join('i.application', 'a')
            ->join('a.jobOffer', 'jo')
            ->addSelect('a', 'jo')
            ->where('jo.createdBy = :rhId')
            ->andWhere('i.isDeleted = true')
            ->setParameter('rhId', $rh->getId())
            ->orderBy('i.interviewDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find one interview by RH including deleted (for restore/permanent delete)
     */
    public function findOneByRhIncludingDeleted(int $id, DbUser $rh): ?Interview
    {
        return $this->createQueryBuilder('i')
            ->join('i.application', 'a')
            ->join('a.jobOffer', 'jo')
            ->addSelect('a', 'jo')
            ->where('i.id = :id')
            ->andWhere('jo.createdBy = :rhId')
            ->setParameter('id', $id)
            ->setParameter('rhId', $rh->getId())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find interviews by candidate
     * @return Interview[]
     */
    public function findByCandidate(int $candidateId): array
    {
        return $this->createQueryBuilder('i')
            ->join('i.application', 'a')
            ->join('a.jobOffer', 'jo')
            ->addSelect('a', 'jo')
            ->where('a.candidate = :candidateId')
            ->andWhere('i.isDeleted = false')
            ->setParameter('candidateId', $candidateId)
            ->orderBy('i.interviewDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find upcoming interviews by candidate
     * @return Interview[]
     */
    public function findUpcomingByCandidate(int $candidateId): array
    {
        return $this->createQueryBuilder('i')
            ->join('i.application', 'a')
            ->join('a.jobOffer', 'jo')
            ->addSelect('a', 'jo')
            ->where('a.candidate = :candidateId')
            ->andWhere('i.isDeleted = false')
            ->andWhere('i.interviewDate >= :now')
            ->setParameter('candidateId', $candidateId)
            ->setParameter('now', new \DateTime())
            ->orderBy('i.interviewDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check for conflicting interviews for an interviewer
     * Returns conflicting interviews within the time range (default 1 hour buffer)
     *
     * @return Interview[]
     */
    public function findConflictingInterviews(
        int $interviewerId,
        \DateTimeInterface $interviewDate,
        ?int $excludeInterviewId = null,
        int $bufferMinutes = 60
    ): array {
        $startTime = \DateTime::createFromInterface($interviewDate)->modify("-{$bufferMinutes} minutes");
        $endTime = \DateTime::createFromInterface($interviewDate)->modify("+{$bufferMinutes} minutes");

        $qb = $this->createQueryBuilder('i')
            ->where('i.interviewerId = :interviewerId')
            ->andWhere('i.isDeleted = false')
            ->andWhere('i.interviewDate BETWEEN :startTime AND :endTime')
            ->setParameter('interviewerId', $interviewerId)
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime)
            ->orderBy('i.interviewDate', 'ASC');

        if ($excludeInterviewId) {
            $qb->andWhere('i.id != :excludeId')
               ->setParameter('excludeId', $excludeInterviewId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Check if interviewer has any conflicts at the given time
     */
    public function hasConflictingInterviews(
        int $interviewerId,
        \DateTimeInterface $interviewDate,
        ?int $excludeInterviewId = null,
        int $bufferMinutes = 60
    ): bool {
        $conflicts = $this->findConflictingInterviews(
            $interviewerId,
            $interviewDate,
            $excludeInterviewId,
            $bufferMinutes
        );

        return count($conflicts) > 0;
    }

    /**
     * Get interviewer's schedule for a specific date
     * @return Interview[]
     */
    public function findInterviewerScheduleForDate(int $interviewerId, \DateTimeInterface $date): array
    {
        $startOfDay = \DateTime::createFromInterface($date)->setTime(0, 0, 0);
        $endOfDay = \DateTime::createFromInterface($date)->setTime(23, 59, 59);

        return $this->createQueryBuilder('i')
            ->join('i.application', 'a')
            ->addSelect('a')
            ->where('i.interviewerId = :interviewerId')
            ->andWhere('i.isDeleted = false')
            ->andWhere('i.interviewDate BETWEEN :startOfDay AND :endOfDay')
            ->setParameter('interviewerId', $interviewerId)
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->orderBy('i.interviewDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
