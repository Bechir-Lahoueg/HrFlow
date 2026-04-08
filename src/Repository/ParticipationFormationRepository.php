<?php

namespace App\Repository;

use App\Entity\ParticipationFormation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParticipationFormation>
 */
class ParticipationFormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParticipationFormation::class);
    }

    public function findByEmployeeAndSession(int $employeeId, int $sessionId): ?ParticipationFormation
    {
        return $this->createQueryBuilder('p')
            ->where('p.employee = :employeeId')
            ->andWhere('p.session = :sessionId')
            ->setParameter('employeeId', $employeeId)
            ->setParameter('sessionId', $sessionId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return ParticipationFormation[] */
    public function findByEmployee(int $employeeId): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.session', 's')
            ->join('s.formation', 'f')
            ->where('p.employee = :employeeId')
            ->setParameter('employeeId', $employeeId)
            ->orderBy('p.dateInscription', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return ParticipationFormation[] */
    public function findBySession(int $sessionId): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.employee', 'e')
            ->where('p.session = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->orderBy('e.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return ParticipationFormation[] */
    public function findAcceptedBySession(int $sessionId): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.employee', 'e')
            ->where('p.session = :sessionId')
            ->andWhere('p.statutParticipation = :statut')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('statut', 'Accepte')
            ->orderBy('e.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
