<?php

namespace App\Repository\Formation;

use App\Entity\Formation\SessionFeedback;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SessionFeedback>
 */
class SessionFeedbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SessionFeedback::class);
    }

    public function hasFeedbackForSessionAndUser(int $sessionId, int $userId): bool
    {
        $count = (int) $this->createQueryBuilder('sf')
            ->select('COUNT(sf.id)')
            ->where('sf.session = :sessionId')
            ->andWhere('sf.employee = :userId')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /** @return SessionFeedback[] */
    public function findByFormation(int $formationId): array
    {
        return $this->createQueryBuilder('sf')
            ->leftJoin('sf.employee', 'e')->addSelect('e')
            ->leftJoin('sf.session', 's')->addSelect('s')
            ->where('sf.formation = :formationId')
            ->setParameter('formationId', $formationId)
            ->orderBy('sf.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getAverageMapByFormationIds(array $formationIds): array
    {
        if ($formationIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('sf')
            ->select('f.id AS formationId, AVG(sf.rating) AS averageRating, COUNT(sf.id) AS totalFeedbacks')
            ->join('sf.formation', 'f')
            ->where('f.id IN (:formationIds)')
            ->setParameter('formationIds', $formationIds)
            ->groupBy('f.id')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['formationId']] = [
                'average' => round((float) $row['averageRating'], 1),
                'count' => (int) $row['totalFeedbacks'],
            ];
        }

        return $map;
    }
}


