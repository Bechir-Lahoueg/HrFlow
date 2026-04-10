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
        $this->autoUpdateStatuses();

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
        $em = $this->getEntityManager();
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        // Planifiee -> En cours
        $em->createQuery(
            "UPDATE App\Entity\Formation\SessionFormation s SET s.statut = 'En cours' WHERE s.statut = 'Planifiee' AND s.dateDebut <= :today"
        )
        ->setParameter('today', $today)
        ->execute();

        // En cours|Planifiee -> Terminee
        $em->createQuery(
            "UPDATE App\Entity\Formation\SessionFormation s SET s.statut = 'Terminee' WHERE (s.statut = 'En cours' OR s.statut = 'Planifiee') AND s.dateFin < :today"
        )
        ->setParameter('today', $today)
        ->execute();
    }
}
