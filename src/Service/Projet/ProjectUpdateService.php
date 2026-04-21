<?php

namespace App\Service\Projet;

use Doctrine\DBAL\Connection;

final class ProjectUpdateService
{
    private ?bool $hasActorSourceColumn = null;

    public function __construct(private readonly Connection $connection) {}

    // ═══════════════════════════════════════════════════════════════
    // CRUD UPDATES (Activités)
    // ═══════════════════════════════════════════════════════════════

    public function getUpdatesByProject(int $projectId, int $limit = 50, ?int $authorUserId = null, bool $includeProjectRhUpdates = false): array
    {
        $safeLimit = max(1, min(200, $limit));
        $authorUserId = $authorUserId !== null && $authorUserId > 0 ? $authorUserId : null;
        $hasActorSource = $this->supportsActorSourceColumn();

        try {
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

            $params = [
                'projectId' => $projectId,
            ];

            if ($authorUserId !== null) {
                if ($includeProjectRhUpdates && $hasActorSource) {
                    $sql .= " AND (
                        u.user_id = :authorUserId
                        OR u.actor_source = 'rh'
                    )";
                } elseif ($includeProjectRhUpdates) {
                    $sql .= " AND (
                        u.user_id = :authorUserId
                        OR LEFT(COALESCE(u.update_type, ''), 3) = 'rh_'
                    )";
                } else {
                    $sql .= ' AND u.user_id = :authorUserId';
                }
                $params['authorUserId'] = $authorUserId;
            }

            $sql .= " ORDER BY u.created_at DESC LIMIT {$safeLimit}";

            return $this->connection->fetchAllAssociative($sql, $params);
        } catch (\Throwable) {
            // Fallback defensif: evite une liste vide si la requete enrichie echoue.
            $fallbackSql = "SELECT u.*, 'Systeme' AS author_name
                            FROM project_updates u
                            WHERE u.project_id = :projectId
                            ORDER BY u.created_at DESC
                            LIMIT {$safeLimit}";

            return $this->connection->fetchAllAssociative($fallbackSql, ['projectId' => $projectId]);
        }
    }

    public function createUpdate(array $data): void
        {
            $insertData = [
                'project_id'  => $data['project_id'],
                'user_id'     => $data['user_id'],
                'update_type' => $data['update_type'] ?? 'comment',
                'title'       => $data['title'],
                'content'     => $data['content'] ?? null,
                'created_at'  => date('Y-m-d H:i:s'),
            ];

            if ($this->supportsActorSourceColumn()) {
                $source = (string) ($data['actor_source'] ?? 'employee');
                $insertData['actor_source'] = $source === 'rh' ? 'rh' : 'employee';
            }

            $this->connection->insert('project_updates', $insertData);
        }

    public function deleteUpdate(int $id): void
    {
        $this->connection->delete('project_updates', ['id' => $id]);
    }

    // ═══════════════════════════════════════════════════════════════
    // LOGS AUTOMATIQUES
    // ═══════════════════════════════════════════════════════════════

    public function logTaskCreated(int $projectId, int $employeeId, string $taskTitle, bool $isRh = false): void
        {
            $this->createUpdate([
                'project_id'  => $projectId,
                'user_id'     => $employeeId,
                'update_type' => $isRh ? 'rh_task' : 'task',
                'actor_source' => $isRh ? 'rh' : 'employee',
                'title'       => 'Nouvelle tâche créée',
                'content'     => "Tâche '{$taskTitle}' ajoutée au projet",
            ]);
        }

    public function logTaskCompleted(int $projectId, int $userId, string $taskTitle, bool $isRh = false): void
    {
        $this->createUpdate([
            'project_id' => $projectId,
            'user_id' => $userId,
            'update_type' => $isRh ? 'rh_task' : 'task',
            'actor_source' => $isRh ? 'rh' : 'employee',
            'title' => 'Tâche terminée',
            'content' => "Tâche '{$taskTitle}' marquée comme terminée",
        ]);
    }

    public function logMilestoneCompleted(int $projectId, int $userId, string $milestoneName, bool $isRh = false): void
    {
        $this->createUpdate([
            'project_id' => $projectId,
            'user_id' => $userId,
            'update_type' => $isRh ? 'rh_milestone' : 'milestone',
            'actor_source' => $isRh ? 'rh' : 'employee',
            'title' => 'Jalon atteint',
            'content' => "Le jalon '{$milestoneName}' a été complété 🎉",
        ]);
    }

    public function logCollaboratorAdded(int $projectId, int $userId, string $collaboratorName): void
    {
        $this->createUpdate([
            'project_id' => $projectId,
            'user_id' => $userId,
            'update_type' => 'comment',
            'title' => 'Nouveau membre',
            'content' => "{$collaboratorName} a rejoint l'équipe",
        ]);
    }

    public function logStatusChange(int $projectId, int $employeeId, string $oldStatus, string $newStatus): void
        {
            $this->createUpdate([
                'project_id'  => $projectId,
                'user_id'     => $employeeId,
                'update_type' => 'status_change',
                'title'       => 'Statut modifié',
                'content'     => "Statut passé de '{$oldStatus}' à '{$newStatus}'",
            ]);
        }

    // ═══════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════

    public function getTypeIcon(string $type): string
    {
        return match($type) {
            'comment', 'rh_comment' => '💬',
            'task', 'rh_task' => '✅',
            'milestone', 'rh_milestone' => '🎯',
            'document' => '📎',
            'status_change', 'rh_status_change' => '🔄',
            default => '📌',
        };
    }

    public function getTypeLabel(string $type): string
    {
        return match($type) {
            'comment' => 'Commentaire',
            'rh_comment' => 'Commentaire RH',
            'task' => 'Tâche',
            'rh_task' => 'Action RH',
            'milestone' => 'Jalon',
            'rh_milestone' => 'Jalon RH',
            'document' => 'Document',
            'status_change', 'rh_status_change' => 'Changement de statut',
            default => $type,
        };
    }

    public function getTypeBadgeClass(string $type): string
    {
        return match($type) {
            'comment', 'rh_comment' => 'badge bg-info',
            'task', 'rh_task' => 'badge bg-success',
            'milestone', 'rh_milestone' => 'badge bg-primary',
            'document' => 'badge bg-warning',
            'status_change', 'rh_status_change' => 'badge bg-secondary',
            default => 'badge bg-light',
        };
    }

    private function supportsActorSourceColumn(): bool
    {
        if ($this->hasActorSourceColumn !== null) {
            return $this->hasActorSourceColumn;
        }

        try {
            $columns = $this->connection->createSchemaManager()->listTableColumns('project_updates');
            $this->hasActorSourceColumn = array_key_exists('actor_source', $columns);
        } catch (\Throwable) {
            $this->hasActorSourceColumn = false;
        }

        return $this->hasActorSourceColumn;
    }
}