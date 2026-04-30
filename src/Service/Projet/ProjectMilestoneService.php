<?php

namespace App\Service\Projet;

use App\Repository\Projet\ProjectMilestoneRepository;

final class ProjectMilestoneService
{
    public function __construct(private readonly ProjectMilestoneRepository $milestoneRepository) {}

    // ═══════════════════════════════════════════════════════════════
    // CRUD JALONS
    // ═══════════════════════════════════════════════════════════════

    public function getMilestonesByProject(int $projectId): array
    {
        try {
            return $this->milestoneRepository->fetchByProject($projectId);
        } catch (\Throwable) {
            return [];
        }
    }

    public function getMilestoneById(int $id): ?array
    {
        try {
            return $this->milestoneRepository->fetchById($id);
        } catch (\Throwable) {
            return null;
        }
    }

    public function createMilestone(array $data): void
    {
        $this->milestoneRepository->insertMilestone([
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
        $this->milestoneRepository->updateMilestone($id, [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'target_date' => $data['target_date'],
            'completion_rate' => $data['completion_rate'] ?? 0,
        ]);

        // Si completion_rate = 100, marquer comme complété
        if (isset($data['completion_rate']) && (int) $data['completion_rate'] >= 100) {
            $this->markAsCompleted($id);
        }
    }

    public function markAsCompleted(int $id): void
    {
        $milestone = $this->getMilestoneById($id);
        if (!$milestone || $milestone['status'] === 'completed') {
            return;
        }

        $this->milestoneRepository->markCompleted($id);
    }

    public function deleteMilestone(int $id): void
    {
        $this->milestoneRepository->deleteMilestone($id);
    }

    // ═══════════════════════════════════════════════════════════════
    // STATISTIQUES
    // ═══════════════════════════════════════════════════════════════

    public function getProjectMilestoneStats(int $projectId): array
    {
        try {
            $total = $this->milestoneRepository->countByProject($projectId);
            $completed = $this->milestoneRepository->countCompletedByProject($projectId);
            $overdue = $this->milestoneRepository->countOverdueByProject($projectId);

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