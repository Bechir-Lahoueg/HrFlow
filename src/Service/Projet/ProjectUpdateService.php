<?php

namespace App\Service\Projet;

use App\Repository\Projet\ProjectUpdateRepository;

final class ProjectUpdateService
{
    public function __construct(private readonly ProjectUpdateRepository $updateRepository) {}

    // ═══════════════════════════════════════════════════════════════
    // CRUD UPDATES (Activités)
    // ═══════════════════════════════════════════════════════════════

    /** @return array<int, array<string, mixed>> */
    public function getUpdatesByProject(int $projectId, int $limit = 50, ?int $authorUserId = null, bool $includeProjectRhUpdates = false): array
    {
        try {
            return $this->updateRepository->fetchByProject($projectId, $limit, $authorUserId, $includeProjectRhUpdates);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<string, mixed> $data */
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

        if ($this->updateRepository->supportsActorSourceColumn()) {
            $source = (string) ($data['actor_source'] ?? 'employee');
            $insertData['actor_source'] = $source === 'rh' ? 'rh' : 'employee';
        }

        $this->updateRepository->insertUpdate($insertData);
    }

    public function deleteUpdate(int $id): void
    {
        $this->updateRepository->deleteUpdate($id);
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
}