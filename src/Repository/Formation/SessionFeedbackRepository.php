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

    /**
     * @param int[] $formationIds
     * @return array<int, array{average: float, count: int}>
     */
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

    public function deleteBySessionId(int $sessionId): int
    {
        return $this->getEntityManager()->createQuery(
            'DELETE FROM App\\Entity\\Formation\\SessionFeedback sf WHERE sf.session = :sessionId'
        )
            ->setParameter('sessionId', $sessionId)
            ->execute();
    }

    public function deleteByFormationId(int $formationId): int
    {
        return $this->getEntityManager()->createQuery(
            'DELETE FROM App\\Entity\\Formation\\SessionFeedback sf WHERE sf.formation = :formationId'
        )
            ->setParameter('formationId', $formationId)
            ->execute();
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchByUser(int $userId): array
    {
        return $this->getConnection()->fetchAllAssociative(
            "SELECT ff.*,
                CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                f.titre AS formation_name,
                CONCAT(sf.date_debut, ' — ', sf.lieu) AS session_name
            FROM feedback_formation ff
            LEFT JOIN employees        e  ON ff.user_id      = e.id
            LEFT JOIN formation         f  ON ff.formation_id = f.id_formation
            LEFT JOIN session_formation sf ON ff.session_id   = sf.id_session
            WHERE ff.user_id = ?
            ORDER BY ff.created_at DESC",
            [$userId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchByRhId(int $rhId): array
    {
        return $this->getConnection()->fetchAllAssociative(
            "SELECT ff.*,
                CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                f.titre AS formation_name,
                CONCAT(sf.date_debut, ' — ', sf.lieu) AS session_name
            FROM feedback_formation ff
            INNER JOIN employees        e  ON ff.user_id      = e.id
            LEFT JOIN  formation         f  ON ff.formation_id = f.id_formation
            LEFT JOIN  session_formation sf ON ff.session_id   = sf.id_session
            WHERE e.rh_id = ?
            ORDER BY ff.created_at DESC",
            [$rhId]
        );
    }

    /** @return array<string, mixed>|null */
    public function fetchById(int $id): ?array
    {
        $row = $this->getConnection()->fetchAssociative(
            "SELECT ff.*,
                CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                f.titre AS formation_name,
                CONCAT(sf.date_debut, ' — ', sf.lieu) AS session_name
            FROM feedback_formation ff
            LEFT JOIN employees        e  ON ff.user_id      = e.id
            LEFT JOIN formation         f  ON ff.formation_id = f.id_formation
            LEFT JOIN session_formation sf ON ff.session_id   = sf.id_session
            WHERE ff.id = ?",
            [$id]
        );

        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function insertFeedback(array $data): void
    {
        $this->getConnection()->insert('feedback_formation', $data);
    }

    /** @param array<string, mixed> $data */
    public function updateFeedback(int $id, array $data): void
    {
        $this->getConnection()->update('feedback_formation', $data, ['id' => $id]);
    }

    public function deleteFeedback(int $id): void
    {
        $this->getConnection()->delete('feedback_formation', ['id' => $id]);
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchApprovedFormations(int $employeeId): array
    {
        return $this->getConnection()->fetchAllAssociative(
            "SELECT DISTINCT f.id_formation, f.titre
            FROM formation f
            JOIN session_formation s ON f.id_formation = s.id_formation
            JOIN participation_formation p ON s.id_session = p.id_session
            WHERE p.id_utilisateur = ? AND p.statut_participation = 'Accepte'
            ORDER BY f.titre",
            [$employeeId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchApprovedSessionsForFormation(int $formationId, int $employeeId): array
    {
        return $this->getConnection()->fetchAllAssociative(
            "SELECT s.id_session, s.date_debut, s.lieu
            FROM session_formation s
            JOIN participation_formation p ON s.id_session = p.id_session
            WHERE s.id_formation = ? AND p.id_utilisateur = ? AND p.statut_participation = 'Accepte'",
            [$formationId, $employeeId]
        );
    }

    public function getAverageRating(int $formationId): float
    {
        return (float) $this->getConnection()->fetchOne(
            'SELECT AVG(rating) FROM feedback_formation WHERE formation_id = ?',
            [$formationId]
        );
    }

    private function getConnection(): \Doctrine\DBAL\Connection
    {
        return $this->getEntityManager()->getConnection();
    }
}
