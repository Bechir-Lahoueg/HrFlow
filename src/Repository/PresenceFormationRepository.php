<?php

namespace App\Repository;

use App\Entity\PresenceFormation;
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
}
