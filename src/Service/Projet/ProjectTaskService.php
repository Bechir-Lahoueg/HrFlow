<?php

namespace App\Service\Projet;

use Doctrine\DBAL\Connection;

final class ProjectTaskService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ProjectService $projectService
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // CRUD TÂCHES
    // ═══════════════════════════════════════════════════════════════

    public function getTasksByProject(int $projectId): array
    {
        try {
            return $this->connection->fetchAllAssociative(
                "SELECT t.*, CONCAT(e.first_name, ' ', e.last_name) as assigned_to_name
                FROM project_tasks t
                LEFT JOIN employees e ON t.assigned_to = e.id
                WHERE t.project_id = ?
                ORDER BY t.order_index ASC, t.created_at DESC",
                [$projectId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function getTaskById(int $id): ?array
    {
        try {
            return $this->connection->fetchAssociative(
                "SELECT t.*, CONCAT(e.first_name, ' ', e.last_name) as assigned_to_name
                FROM project_tasks t
                LEFT JOIN employees e ON t.assigned_to = e.id
                WHERE t.id = ?",
                [$id]
            ) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function createTask(array $data): void
    {
        // Obtenir le prochain order_index
        $maxOrder = $this->connection->fetchOne(
            'SELECT COALESCE(MAX(order_index), 0) FROM project_tasks WHERE project_id = ?',
            [$data['project_id']]
        );

        $this->connection->insert('project_tasks', [
            'project_id' => $data['project_id'],
            'assigned_to' => $data['assigned_to'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'todo',
            'priority' => $data['priority'] ?? 'medium',
            'estimated_hours' => $data['estimated_hours'] ?? 0,
            'actual_hours' => 0,
            'due_date' => $data['due_date'] ?? null,
            'completed_date' => null,
            'order_index' => (int)$maxOrder + 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Mettre à jour le taux d'avancement du projet
        $this->projectService->updateCompletionRate($data['project_id']);
    }

    public function updateTask(int $id, array $data): void
    {
        $task = $this->getTaskById($id);
        if (!$task) {
            return;
        }

        $updateData = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'priority' => $data['priority'],
            'assigned_to' => $data['assigned_to'] ?? null,
            'estimated_hours' => $data['estimated_hours'] ?? 0,
            'due_date' => $data['due_date'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Si la tâche passe à "done", enregistrer la date
        if ($data['status'] === 'done' && $task['status'] !== 'done') {
            $updateData['completed_date'] = date('Y-m-d');
        }

        $this->connection->update('project_tasks', $updateData, ['id' => $id]);

        // Mettre à jour le taux d'avancement du projet
        $this->projectService->updateCompletionRate($task['project_id']);
    }

    public function updateTaskStatus(int $id, string $status): void
    {
        $task = $this->getTaskById($id);
        if (!$task) {
            return;
        }

        $updateData = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Si la tâche passe à "done", enregistrer la date
        if ($status === 'done' && $task['status'] !== 'done') {
            $updateData['completed_date'] = date('Y-m-d');
        }

        $this->connection->update('project_tasks', $updateData, ['id' => $id]);

        // Mettre à jour le taux d'avancement du projet
        $this->projectService->updateCompletionRate($task['project_id']);
    }

    public function deleteTask(int $id): void
    {
        $task = $this->getTaskById($id);
        if (!$task) {
            return;
        }

        $projectId = $task['project_id'];

        $this->connection->delete('project_tasks', ['id' => $id]);

        // Mettre à jour le taux d'avancement du projet
        $this->projectService->updateCompletionRate($projectId);
    }

    public function logHours(int $taskId, int $hours): void
    {
        $task = $this->getTaskById($taskId);
        if (!$task) {
            return;
        }

        $newActualHours = (int)$task['actual_hours'] + $hours;

        $this->connection->update('project_tasks', [
            'actual_hours' => $newActualHours,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $taskId]);
    }

    // ═══════════════════════════════════════════════════════════════
    // KANBAN - DRAG & DROP
    // ═══════════════════════════════════════════════════════════════

    public function updateTaskOrder(int $taskId, int $newOrder): void
    {
        $this->connection->update('project_tasks', [
            'order_index' => $newOrder,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $taskId]);
    }

    public function getTasksByStatus(int $projectId): array
    {
        $tasks = $this->getTasksByProject($projectId);

        return [
            'todo' => array_filter($tasks, fn($t) => $t['status'] === 'todo'),
            'in_progress' => array_filter($tasks, fn($t) => $t['status'] === 'in_progress'),
            'done' => array_filter($tasks, fn($t) => $t['status'] === 'done'),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // TÂCHES PAR EMPLOYÉ
    // ═══════════════════════════════════════════════════════════════

    public function getTasksByEmployee(int $employeeId): array
    {
        try {
            return $this->connection->fetchAllAssociative(
                'SELECT t.*, p.name as project_name, p.status as project_status
                FROM project_tasks t
                JOIN projects p ON t.project_id = p.id
                WHERE t.assigned_to = ?
                ORDER BY t.due_date ASC, t.created_at DESC',
                [$employeeId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function getEmployeeTaskStats(int $employeeId): array
    {
        try {
            $total = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM project_tasks WHERE assigned_to = ?',
                [$employeeId]
            );

            $completed = $this->connection->fetchOne(
                "SELECT COUNT(*) FROM project_tasks WHERE assigned_to = ? AND status = 'done'",
                [$employeeId]
            );

            $inProgress = $this->connection->fetchOne(
                "SELECT COUNT(*) FROM project_tasks WHERE assigned_to = ? AND status = 'in_progress'",
                [$employeeId]
            );

            $overdue = $this->connection->fetchOne(
                "SELECT COUNT(*) FROM project_tasks
                WHERE assigned_to = ? AND status != 'done' AND due_date < CURDATE()",
                [$employeeId]
            );

            return [
                'total_tasks' => (int) $total,
                'completed_tasks' => (int) $completed,
                'in_progress_tasks' => (int) $inProgress,
                'overdue_tasks' => (int) $overdue,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100) : 0,
            ];
        } catch (\Throwable) {
            return [
                'total_tasks' => 0,
                'completed_tasks' => 0,
                'in_progress_tasks' => 0,
                'overdue_tasks' => 0,
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
            'todo' => 'À faire',
            'in_progress' => 'En cours',
            'done' => 'Terminé',
            default => $status,
        };
    }

    public function getStatusBadgeClass(string $status): string
    {
        return match($status) {
            'todo' => 'badge bg-secondary',
            'in_progress' => 'badge bg-warning',
            'done' => 'badge bg-success',
            default => 'badge bg-secondary',
        };
    }

    public function isOverdue(array $task): bool
    {
        if (!$task['due_date'] || $task['status'] === 'done') {
            return false;
        }

        return (new \DateTime($task['due_date'])) < (new \DateTime());
    }
}