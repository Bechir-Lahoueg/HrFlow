<?php

namespace App\Repository\Formation;

use App\Entity\Formation\SessionFormation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SessionFormation>
 */
class SessionFormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SessionFormation::class);
    }

    /** @return SessionFormation[] */
    public function findByFormation(int $formationId): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.formation = :formationId')
            ->setParameter('formationId', $formationId)
            ->orderBy('s.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return SessionFormation[] */
    public function findAvailable(): array
    {
        $this->autoUpdateStatuses();

        return $this->createQueryBuilder('s')
            ->join('s.formation', 'f')
            ->where('s.statut = :statut')
            ->andWhere('s.dateDebut > :now')
            ->setParameter('statut', 'Planifiee')
            ->setParameter('now', new \DateTime())
            ->orderBy('s.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function autoUpdateStatuses(): void
    {
        $this->getEntityManager()->createQuery(
            "UPDATE App\Entity\Formation\SessionFormation s SET s.statut = 'Cloturee' WHERE s.dateFin < :now"
        )
        ->setParameter('now', new \DateTime())
        ->execute();
    }
}
