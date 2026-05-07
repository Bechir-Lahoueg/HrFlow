<?php

namespace App\Repository\Projet;

use App\Entity\Projet\ProjectUpdate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectUpdate>
 */
class ProjectUpdateRepository extends ServiceEntityRepository
{
    private ?bool $hasActorSourceColumn = null;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectUpdate::class);
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchByProject(int $projectId, int $limit = 50, ?int $authorUserId = null, bool $includeProjectRhUpdates = false): array
    {
        $safeLimit = max(1, min(200, $limit));
        $authorUserId = $authorUserId !== null && $authorUserId > 0 ? $authorUserId : null;
        $hasActorSource = $this->supportsActorSourceColumn();

        if ($hasActorSource) {
            $sql = "SELECT u.*,
                    CASE
                        WHEN u.actor_source = 'rh' THEN COALESCE(us.username, CONCAT('RH #', u.user_id))
                        ELSE COALESCE(CONCAT(e.first_name, ' ', e.last_name), 'Employe #', u.user_id)
                    END AS author_name
                FROM project_updates u
                LEFT JOIN employees e ON u.user_id = e.id AND (u.actor_source IS NULL OR u.actor_source <> 'rh')
                LEFT JOIN users us ON u.user_id = us.id AND u.actor_source = 'rh'
                WHERE u.project_id = :projectId";
        } else {
            $sql = "SELECT u.*,
                    CASE
                        WHEN LEFT(COALESCE(u.update_type, ''), 3) = 'rh_' THEN COALESCE(us.username, CONCAT('RH #', u.user_id))
                        WHEN e.id IS NOT NULL THEN CONCAT(e.first_name, ' ', e.last_name)
                        WHEN us.id IS NOT NULL THEN us.username
                        ELSE 'Systeme'
                    END AS author_name
                FROM project_updates u
                INNER JOIN projects p ON p.id = u.project_id
                LEFT JOIN employees e ON u.user_id = e.id
                LEFT JOIN users us ON u.user_id = us.id
                WHERE u.project_id = :projectId";
        }

        $params = ['projectId' => $projectId];

        if ($authorUserId !== null) {
            if ($includeProjectRhUpdates && $hasActorSource) {
                $sql .= " AND (u.user_id = :authorUserId OR u.actor_source = 'rh')";
            } elseif ($includeProjectRhUpdates) {
                $sql .= " AND (u.user_id = :authorUserId OR LEFT(COALESCE(u.update_type, ''), 3) = 'rh_')";
            } else {
                $sql .= ' AND u.user_id = :authorUserId';
            }
            $params['authorUserId'] = $authorUserId;
        }

        $sql .= " ORDER BY u.created_at DESC LIMIT {$safeLimit}";

        try {
            return $this->getConnection()->fetchAllAssociative($sql, $params);
        } catch (\Throwable) {
            $fallbackSql = "SELECT u.*, 'Systeme' AS author_name
                FROM project_updates u
                WHERE u.project_id = :projectId
                ORDER BY u.created_at DESC
                LIMIT {$safeLimit}";

            return $this->getConnection()->fetchAllAssociative($fallbackSql, ['projectId' => $projectId]);
        }
    }

    /** @param array<string, mixed> $data */
    public function insertUpdate(array $data): void
    {
        $this->getConnection()->insert('project_updates', $data);
    }

    public function deleteUpdate(int $id): void
    {
        $this->getConnection()->delete('project_updates', ['id' => $id]);
    }

    public function supportsActorSourceColumn(): bool
    {
        if ($this->hasActorSourceColumn !== null) {
            return $this->hasActorSourceColumn;
        }

        try {
            $columns = $this->getConnection()->createSchemaManager()->listTableColumns('project_updates');
            $this->hasActorSourceColumn = array_key_exists('actor_source', $columns);
        } catch (\Throwable) {
            $this->hasActorSourceColumn = false;
        }

        return $this->hasActorSourceColumn;
    }

    private function getConnection(): Connection
    {
        return $this->getEntityManager()->getConnection();
    }
}

