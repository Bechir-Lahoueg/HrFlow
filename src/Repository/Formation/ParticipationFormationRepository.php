<?php

namespace App\Repository\Formation;

use App\Entity\Formation\ParticipationFormation;
use App\Entity\Rh\Employee;
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
            ->join('p.session', 's')
            ->join('s.formation', 'f')
            ->addSelect('CASE WHEN e.rhId = f.rhId THEN 0 ELSE 1 END AS HIDDEN priorityRank')
            ->where('p.session = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->orderBy('priorityRank', 'ASC')
            ->addOrderBy('p.dateInscription', 'ASC')
            ->addOrderBy('e.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return ParticipationFormation[] */
    public function findByRhId(int $rhId, string $status = '', ?int $formationId = null, bool $priorityOnly = false): array
    {
        $qb = $this->createQueryBuilder('p')
            ->join('p.employee', 'e')
            ->join('p.session', 's')
            ->join('s.formation', 'f')
            ->where('f.rhId = :rhId')
            ->setParameter('rhId', $rhId);

        if ($status) {
            $qb->andWhere('p.statutParticipation = :status')
               ->setParameter('status', $status);
        }

        if ($formationId !== null) {
            $qb->andWhere('f.id = :formationId')
               ->setParameter('formationId', $formationId);
        }

        if ($priorityOnly) {
            $qb->andWhere('e.rhId = f.rhId');
        }

        return $qb
            ->addSelect('CASE WHEN e.rhId = f.rhId THEN 0 ELSE 1 END AS HIDDEN priorityRank')
            ->orderBy('priorityRank', 'ASC')
            ->addOrderBy('p.dateInscription', 'ASC')
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

    /** @return Employee[] */
    public function findNotifiableEmployeesByFormation(int $formationId): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT e')
            ->from(Employee::class, 'e')
            ->join(ParticipationFormation::class, 'p', 'WITH', 'p.employee = e')
            ->join('p.session', 's')
            ->where('s.formation = :formationId')
            ->andWhere('p.statutParticipation IN (:statuses)')
            ->setParameter('formationId', $formationId)
            ->setParameter('statuses', ['Inscrit', 'Accepte', 'Certificat obtenu'])
            ->orderBy('e.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Employee[] */
    public function findNotifiableEmployeesBySession(int $sessionId): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT e')
            ->from(Employee::class, 'e')
            ->join(ParticipationFormation::class, 'p', 'WITH', 'p.employee = e')
            ->where('p.session = :sessionId')
            ->andWhere('p.statutParticipation IN (:statuses)')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('statuses', ['Inscrit', 'Accepte', 'Certificat obtenu'])
            ->orderBy('e.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function hasAcceptedInFormation(int $employeeId, int $formationId): bool
    {
        $count = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->join('p.session', 's')
            ->where('p.employee = :employeeId')
            ->andWhere('s.formation = :formationId')
            ->andWhere('p.statutParticipation IN (:statuses)')
            ->setParameter('employeeId', $employeeId)
            ->setParameter('formationId', $formationId)
            ->setParameter('statuses', ['Accepte', 'Certificat obtenu'])
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function findAcceptedInFormationExcludingSession(int $employeeId, int $formationId, int $excludedSessionId): ?ParticipationFormation
    {
        return $this->createQueryBuilder('p')
            ->join('p.session', 's')
            ->where('p.employee = :employeeId')
            ->andWhere('s.formation = :formationId')
            ->andWhere('s.id != :excludedSessionId')
            ->andWhere('p.statutParticipation IN (:statuses)')
            ->setParameter('employeeId', $employeeId)
            ->setParameter('formationId', $formationId)
            ->setParameter('excludedSessionId', $excludedSessionId)
            ->setParameter('statuses', ['Accepte', 'Certificat obtenu'])
            ->orderBy('s.dateDebut', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAcceptedWithDateOverlapExcludingSession(int $employeeId, \DateTimeInterface $startDate, \DateTimeInterface $endDate, int $excludedSessionId): ?ParticipationFormation
    {
        return $this->createQueryBuilder('p')
            ->join('p.session', 's')
            ->where('p.employee = :employeeId')
            ->andWhere('s.id != :excludedSessionId')
            ->andWhere('p.statutParticipation IN (:statuses)')
            ->andWhere('NOT (s.dateFin < :startDate OR s.dateDebut > :endDate)')
            ->setParameter('employeeId', $employeeId)
            ->setParameter('excludedSessionId', $excludedSessionId)
            ->setParameter('statuses', ['Accepte', 'Certificat obtenu'])
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('s.dateDebut', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return ParticipationFormation[] */
    public function findPendingInFormationExcludingSession(int $employeeId, int $formationId, int $excludedSessionId): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.session', 's')
            ->where('p.employee = :employeeId')
            ->andWhere('s.formation = :formationId')
            ->andWhere('s.id != :excludedSessionId')
            ->andWhere('p.statutParticipation = :pendingStatus')
            ->setParameter('employeeId', $employeeId)
            ->setParameter('formationId', $formationId)
            ->setParameter('excludedSessionId', $excludedSessionId)
            ->setParameter('pendingStatus', 'Inscrit')
            ->getQuery()
            ->getResult();
    }

    /** @return ParticipationFormation[] */
    public function findPendingWithDateOverlapExcludingSession(int $employeeId, \DateTimeInterface $startDate, \DateTimeInterface $endDate, int $excludedSessionId, ?int $excludedFormationId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->join('p.session', 's')
            ->join('s.formation', 'f')
            ->where('p.employee = :employeeId')
            ->andWhere('s.id != :excludedSessionId')
            ->andWhere('p.statutParticipation = :pendingStatus')
            ->andWhere('NOT (s.dateFin < :startDate OR s.dateDebut > :endDate)')
            ->setParameter('employeeId', $employeeId)
            ->setParameter('excludedSessionId', $excludedSessionId)
            ->setParameter('pendingStatus', 'Inscrit')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        if ($excludedFormationId !== null) {
            $qb->andWhere('f.id != :excludedFormationId')
                ->setParameter('excludedFormationId', $excludedFormationId);
        }

        return $qb->getQuery()->getResult();
    }

    public function countAcceptedBySession(int $sessionId): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.session = :sessionId')
            ->andWhere('p.statutParticipation IN (:statuses)')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('statuses', ['Accepte', 'Certificat obtenu'])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasPendingPriorityBySessionRh(int $sessionId, int $rhId): bool
    {
        $count = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->join('p.employee', 'e')
            ->where('p.session = :sessionId')
            ->andWhere('p.statutParticipation = :status')
            ->andWhere('e.rhId = :rhId')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('status', 'Inscrit')
            ->setParameter('rhId', $rhId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function deleteBySessionId(int $sessionId): int
    {
        return $this->getEntityManager()->createQuery(
            'DELETE FROM App\\Entity\\Formation\\ParticipationFormation p WHERE p.session = :sessionId'
        )
            ->setParameter('sessionId', $sessionId)
            ->execute();
    }

    public function deleteByFormationId(int $formationId): int
    {
        return $this->getEntityManager()->createQuery(
            'DELETE FROM App\\Entity\\Formation\\ParticipationFormation p
             WHERE p.session IN (
                SELECT s_sub.id FROM App\\Entity\\Formation\\SessionFormation s_sub WHERE s_sub.formation = :formationId
             )'
        )
            ->setParameter('formationId', $formationId)
            ->execute();
    }
}
