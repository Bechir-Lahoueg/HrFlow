<?php

namespace App\Repository\Formation;

use App\Entity\Formation\PresenceFormation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PresenceFormation>
 */
class PresenceFormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PresenceFormation::class);
    }

    /** @return PresenceFormation[] */
    public function findBySession(int $sessionId): array
    {
        return $this->createQueryBuilder('pr')
            ->join('pr.participation', 'p')
            ->where('p.session = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->getQuery()
            ->getResult();
    }

    public function findOneByParticipationAndDate(int $participationId, string $date): ?PresenceFormation
    {
        return $this->createQueryBuilder('pr')
            ->where('pr.participation = :pid')
            ->andWhere('pr.datePresence = :date')
            ->setParameter('pid', $participationId)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByParticipation(int $participationId): int
    {
        return (int) $this->createQueryBuilder('pr')
            ->select('COUNT(pr.id)')
            ->where('pr.participation = :pid')
            ->setParameter('pid', $participationId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPresentByParticipation(int $participationId): int
    {
        return (int) $this->createQueryBuilder('pr')
            ->select('COUNT(pr.id)')
            ->where('pr.participation = :pid')
            ->andWhere('pr.statut IN (:statuts)')
            ->setParameter('pid', $participationId)
            ->setParameter('statuts', ['Present', 'Justifie'])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function deleteBySessionId(int $sessionId): int
    {
        return $this->getEntityManager()->createQuery(
            'DELETE FROM App\\Entity\\Formation\\PresenceFormation pr
             WHERE pr.participation IN (
                SELECT p_sub.id FROM App\\Entity\\Formation\\ParticipationFormation p_sub WHERE p_sub.session = :sessionId
             )'
        )
            ->setParameter('sessionId', $sessionId)
            ->execute();
    }

    public function deleteByFormationId(int $formationId): int
    {
        return $this->getEntityManager()->createQuery(
            'DELETE FROM App\\Entity\\Formation\\PresenceFormation pr
             WHERE pr.participation IN (
                SELECT p_sub.id FROM App\\Entity\\Formation\\ParticipationFormation p_sub
                JOIN p_sub.session s_sub
                WHERE s_sub.formation = :formationId
             )'
        )
            ->setParameter('formationId', $formationId)
            ->execute();
    }

    /**
     * @return array<int, array{presenceDate:\DateTimeInterface, attendanceCount:string|int}>
     */
    public function countWeeklyAttendanceByEmployee(int $employeeId, \DateTimeInterface $weekStart, \DateTimeInterface $weekEnd): array
    {
        return $this->createQueryBuilder('pr')
            ->select('pr.datePresence AS presenceDate', 'COUNT(pr.id) AS attendanceCount')
            ->join('pr.participation', 'p')
            ->where('p.employee = :employeeId')
            ->andWhere('pr.datePresence BETWEEN :weekStart AND :weekEnd')
            ->andWhere('pr.statut IN (:statuts)')
            ->setParameter('employeeId', $employeeId)
            ->setParameter('weekStart', $weekStart)
            ->setParameter('weekEnd', $weekEnd)
            ->setParameter('statuts', ['Present', 'Justifie'])
            ->groupBy('pr.datePresence')
            ->orderBy('pr.datePresence', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
