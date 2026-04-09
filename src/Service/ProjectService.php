<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class ProjectService
{
    public function __construct(private readonly Connection $connection) {}

    // ═══════════════════════════════════════════════════════════════
    // CRUD PROJETS
    // ═══════════════════════════════════════════════════════════════

    public function getAllProjects(): array
    {
        try {
            return $this->connection->fetchAllAssociative(
                'SELECT * FROM projects ORDER BY created_at DESC'
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function getProjectsByRh(int $rhId): array
    {
        try {
            return $this->connection->fetchAllAssociative(
                'SELECT * FROM projects WHERE rh_id = ? ORDER BY created_at DESC',
                [$rhId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function getProjectById(int $id): ?array
    {
        try {
            return $this->connection->fetchAssociative(
                'SELECT * FROM projects WHERE id = ?',
                [$id]
            ) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function createProject(array $data): void
    {
        $this->connection->insert('projects', [
            'rh_id' => $data['rh_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'priority' => $data['priority'] ?? 'medium',
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'estimated_hours' => $data['estimated_hours'] ?? 0,
            'actual_hours' => 0,
            'budget' => $data['budget'] ?? null,
            'completion_rate' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function updateProject(int $id, array $data): void
    {
        $updateData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'priority' => $data['priority'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'estimated_hours' => $data['estimated_hours'] ?? 0,
            'budget' => $data['budget'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->connection->update('projects', $updateData, ['id' => $id]);
    }

    public function deleteProject(int $id): void
    {
        // Supprimer en cascade (collaborateurs, tâches, jalons, updates)
        $this->connection->delete('project_collaborators', ['project_id' => $id]);
        $this->connection->delete('project_tasks', ['project_id' => $id]);
        $this->connection->delete('project_milestones', ['project_id' => $id]);
        $this->connection->delete('project_updates', ['project_id' => $id]);
        $this->connection->delete('projects', ['id' => $id]);
    }

    public function updateCompletionRate(int $projectId): void
    {
        try {
            // Calculer le taux d'avancement basé sur les tâches
            $result = $this->connection->fetchAssociative(
                "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed
                FROM project_tasks
                WHERE project_id = ?",
                [$projectId]
            );

            $total = (int) $result['total'];
            $completed = (int) $result['completed'];

            $completionRate = $total > 0 ? round(($completed / $total) * 100) : 0;

            $this->connection->update('projects', [
                'completion_rate' => $completionRate,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $projectId]);
        } catch (\Throwable) {
            // Ignore
        }
    }

    public function updateActualHours(int $projectId): void
    {
        try {
            // Sommer les heures travaillées des collaborateurs
            $result = $this->connection->fetchOne(
                'SELECT COALESCE(SUM(worked_hours), 0) FROM project_collaborators WHERE project_id = ?',
                [$projectId]
            );

            $this->connection->update('projects', [
                'actual_hours' => (int) $result,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $projectId]);
        } catch (\Throwable) {
            // Ignore
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // STATISTIQUES
    // ═══════════════════════════════════════════════════════════════

    public function getProjectStats(int $rhId): array
    {
        try {
            $total = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM projects WHERE rh_id = ?',
                [$rhId]
            );

            $active = $this->connection->fetchOne(
                "SELECT COUNT(*) FROM projects WHERE rh_id = ? AND status = 'active'",
                [$rhId]
            );

            $completed = $this->connection->fetchOne(
                "SELECT COUNT(*) FROM projects WHERE rh_id = ? AND status = 'completed'",
                [$rhId]
            );

            $onHold = $this->connection->fetchOne(
                "SELECT COUNT(*) FROM projects WHERE rh_id = ? AND status = 'on_hold'",
                [$rhId]
            );

            $totalTasks = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM project_tasks pt
                JOIN projects p ON pt.project_id = p.id
                WHERE p.rh_id = ?',
                [$rhId]
            );

            $completedTasks = $this->connection->fetchOne(
                "SELECT COUNT(*) FROM project_tasks pt
                JOIN projects p ON pt.project_id = p.id
                WHERE p.rh_id = ? AND pt.status = 'done'",
                [$rhId]
            );

            return [
                'total_projects' => (int) $total,
                'active_projects' => (int) $active,
                'completed_projects' => (int) $completed,
                'on_hold_projects' => (int) $onHold,
                'total_tasks' => (int) $totalTasks,
                'completed_tasks' => (int) $completedTasks,
                'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0,
            ];
        } catch (\Throwable) {
            return [
                'total_projects' => 0,
                'active_projects' => 0,
                'completed_projects' => 0,
                'on_hold_projects' => 0,
                'total_tasks' => 0,
                'completed_tasks' => 0,
                'completion_rate' => 0,
            ];
        }
    }

    public function getProjectsWithDetails(int $rhId): array
    {
        try {
            $projects = $this->getProjectsByRh($rhId);

            foreach ($projects as &$project) {
                // Nombre de collaborateurs
                $project['team_count'] = (int) $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM project_collaborators WHERE project_id = ? AND is_active = 1',
                    [$project['id']]
                );

                // Nombre de tâches
                $project['tasks_count'] = (int) $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM project_tasks WHERE project_id = ?',
                    [$project['id']]
                );

                // Nombre de tâches terminées
                $project['tasks_completed'] = (int) $this->connection->fetchOne(
                    "SELECT COUNT(*) FROM project_tasks WHERE project_id = ? AND status = 'done'",
                    [$project['id']]
                );

                // Vérifier si en retard
                $project['is_overdue'] = (new \DateTime($project['end_date'])) < (new \DateTime())
                    && $project['status'] !== 'completed';
            }

            return $projects;
        } catch (\Throwable) {
            return [];
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════

    public function getStatusLabel(string $status): string
    {
        return match($status) {
            'pending' => 'En attente',
            'active' => 'Actif',
            'completed' => 'Terminé',
            'on_hold' => 'En pause',
            default => $status,
        };
    }

    public function getPriorityLabel(string $priority): string
    {
        return match($priority) {
            'low' => 'Basse',
            'medium' => 'Moyenne',
            'high' => 'Haute',
            default => $priority,
        };
    }

    public function getStatusBadgeClass(string $status): string
    {
        return match($status) {
            'pending' => 'badge bg-secondary',
            'active' => 'badge bg-primary',
            'completed' => 'badge bg-success',
            'on_hold' => 'badge bg-warning',
            default => 'badge bg-secondary',
        };
    }

    public function getPriorityBadgeClass(string $priority): string
    {
        return match($priority) {
            'low' => 'badge bg-info',
            'medium' => 'badge bg-warning',
            'high' => 'badge bg-danger',
            default => 'badge bg-secondary',
        };
    }
}