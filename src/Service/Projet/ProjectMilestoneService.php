<?php

namespace App\Service\Projet;

use Doctrine\DBAL\Connection;

final class ProjectMilestoneService
{
    public function __construct(private readonly Connection $connection) {}

    // ═══════════════════════════════════════════════════════════════
    // CRUD JALONS
    // ═══════════════════════════════════════════════════════════════

    public function getMilestonesByProject(int $projectId): array
    {
        try {
            return $this->connection->fetchAllAssociative(
                'SELECT * FROM project_milestones
                WHERE project_id = ?
                ORDER BY target_date ASC',
                [$projectId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function getMilestoneById(int $id): ?array
    {
        try {
            return $this->connection->fetchAssociative(
                'SELECT * FROM project_milestones WHERE id = ?',
                [$id]
            ) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function createMilestone(array $data): void
    {
        $this->connection->insert('project_milestones', [
            'project_id' => $data['project_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'target_date' => $data['target_date'],
            'completion_date' => null,
            'status' => 'pending',
            'completion_rate' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function updateMilestone(int $id, array $data): void
    {
        $this->connection->update('project_milestones', [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'target_date' => $data['target_date'],
            'completion_rate' => $data['completion_rate'] ?? 0,
        ], ['id' => $id]);

        // Si completion_rate = 100, marquer comme complété
        if (isset($data['completion_rate']) && (int)$data['completion_rate'] >= 100) {
            $this->markAsCompleted($id);
        }
    }

    public function markAsCompleted(int $id): void
    {
        $milestone = $this->getMilestoneById($id);
        if (!$milestone || $milestone['status'] === 'completed') {
            return;
        }

        $this->connection->update('project_milestones', [
            'status' => 'completed',
            'completion_rate' => 100,
            'completion_date' => date('Y-m-d'),
        ], ['id' => $id]);
    }

    public function deleteMilestone(int $id): void
    {
        $this->connection->delete('project_milestones', ['id' => $id]);
    }

    // ═══════════════════════════════════════════════════════════════
    // STATISTIQUES
    // ═══════════════════════════════════════════════════════════════

    public function getProjectMilestoneStats(int $projectId): array
    {
        try {
            $total = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM project_milestones WHERE project_id = ?',
                [$projectId]
            );

            $completed = $this->connection->fetchOne(
                "SELECT COUNT(*) FROM project_milestones
                WHERE project_id = ? AND status = 'completed'",
                [$projectId]
            );

            $overdue = $this->connection->fetchOne(
                "SELECT COUNT(*) FROM project_milestones
                WHERE project_id = ? AND status != 'completed' AND target_date < CURDATE()",
                [$projectId]
            );

            return [
                'total_milestones' => (int) $total,
                'completed_milestones' => (int) $completed,
                'overdue_milestones' => (int) $overdue,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100) : 0,
            ];
        } catch (\Throwable) {
            return [
                'total_milestones' => 0,
                'completed_milestones' => 0,
                'overdue_milestones' => 0,
                'completion_rate' => 0,
            ];
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════

    public function getStatusLabel(string $status): string
    {
        return match($status) {
            'pending' => 'En attente',
            'in_progress' => 'En cours',
            'completed' => 'Terminé',
            default => $status,
        };
    }

    public function getStatusBadgeClass(string $status): string
    {
        return match($status) {
            'pending' => 'badge bg-secondary',
            'in_progress' => 'badge bg-warning',
            'completed' => 'badge bg-success',
            default => 'badge bg-secondary',
        };
    }

    public function isOverdue(array $milestone): bool
    {
        if ($milestone['status'] === 'completed') {
            return false;
        }

        return (new \DateTime($milestone['target_date'])) < (new \DateTime());
    }
}