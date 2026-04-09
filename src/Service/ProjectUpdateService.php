<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class ProjectUpdateService
{
    public function __construct(private readonly Connection $connection) {}

    // ═══════════════════════════════════════════════════════════════
    // CRUD UPDATES (Activités)
    // ═══════════════════════════════════════════════════════════════

    public function getUpdatesByProject(int $projectId, int $limit = 50): array
    {
        try {
            return $this->connection->fetchAllAssociative(
                "SELECT u.*,
                    CASE
                        WHEN e.id IS NOT NULL THEN CONCAT(e.first_name, ' ', e.last_name)
                        WHEN us.id IS NOT NULL THEN us.username
                        ELSE 'Système'
                    END as author_name
                 FROM project_updates u
                 LEFT JOIN employees e ON u.user_id = e.id
                 LEFT JOIN users us ON u.user_id = us.id
                 WHERE u.project_id = :projectId
                 ORDER BY u.created_at DESC
                 LIMIT :limit",
                [
                    'projectId' => $projectId,
                    'limit' => (int) $limit
                ],
                [
                    'projectId' => \PDO::PARAM_INT,
                    'limit' => \PDO::PARAM_INT
                ]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function createUpdate(array $data): void
        {
            $this->connection->insert('project_updates', [
                'project_id'  => $data['project_id'],
                'user_id'     => $data['user_id'], // C'est l'ID de l'employé
                'update_type' => $data['update_type'] ?? 'comment',
                'title'       => $data['title'],
                'content'     => $data['content'] ?? null,
                // 'created_at' est un TIMESTAMP, MySQL peut le gérer seul,
                // mais on peut le forcer ici si nécessaire :
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        }

    public function deleteUpdate(int $id): void
    {
        $this->connection->delete('project_updates', ['id' => $id]);
    }

    // ═══════════════════════════════════════════════════════════════
    // LOGS AUTOMATIQUES
    // ═══════════════════════════════════════════════════════════════

    public function logTaskCreated(int $projectId, int $employeeId, string $taskTitle): void
        {
            $this->createUpdate([
                'project_id'  => $projectId,
                'user_id'     => $employeeId,
                'update_type' => 'task',
                'title'       => 'Nouvelle tâche créée',
                'content'     => "Tâche '{$taskTitle}' ajoutée au projet",
            ]);
        }

    public function logTaskCompleted(int $projectId, int $userId, string $taskTitle): void
    {
        $this->createUpdate([
            'project_id' => $projectId,
            'user_id' => $userId,
            'update_type' => 'task',
            'title' => 'Tâche terminée',
            'content' => "Tâche '{$taskTitle}' marquée comme terminée",
        ]);
    }

    public function logMilestoneCompleted(int $projectId, int $userId, string $milestoneName): void
    {
        $this->createUpdate([
            'project_id' => $projectId,
            'user_id' => $userId,
            'update_type' => 'milestone',
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
            'comment' => '💬',
            'task' => '✅',
            'milestone' => '🎯',
            'document' => '📎',
            'status_change' => '🔄',
            default => '📌',
        };
    }

    public function getTypeLabel(string $type): string
    {
        return match($type) {
            'comment' => 'Commentaire',
            'task' => 'Tâche',
            'milestone' => 'Jalon',
            'document' => 'Document',
            'status_change' => 'Changement de statut',
            default => $type,
        };
    }

    public function getTypeBadgeClass(string $type): string
    {
        return match($type) {
            'comment' => 'badge bg-info',
            'task' => 'badge bg-success',
            'milestone' => 'badge bg-primary',
            'document' => 'badge bg-warning',
            'status_change' => 'badge bg-secondary',
            default => 'badge bg-light',
        };
    }
}