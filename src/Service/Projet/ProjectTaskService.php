<?php

namespace App\Service\Projet;

use Doctrine\DBAL\Connection;

final class ProjectTaskService
{
    private ?bool $hasTaskStartDateColumn = null;

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
        $data = $this->normalizeTaskInput($data);

        // Obtenir le prochain order_index
        $maxOrder = $this->connection->fetchOne(
            'SELECT COALESCE(MAX(order_index), 0) FROM project_tasks WHERE project_id = ?',
            [$data['project_id']]
        );

        $insertData = [
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
        ];

        if ($this->supportsTaskStartDate()) {
            $insertData['start_date'] = $data['start_date'] ?? null;
        }

        $this->connection->insert('project_tasks', $insertData);

        // Mettre à jour le taux d'avancement du projet
        $this->projectService->updateCompletionRate($data['project_id']);
    }

    public function updateTask(int $id, array $data): void
    {
        $task = $this->getTaskById($id);
        if (!$task) {
            return;
        }

        $data = $this->normalizeTaskInput($data);

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

        if ($this->supportsTaskStartDate()) {
            $updateData['start_date'] = $data['start_date'] ?? null;
        }

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

    public function assignTaskToEmployee(int $taskId, int $employeeId): bool
    {
        $task = $this->getTaskById($taskId);
        if (!$task) {
            return false;
        }

        $this->connection->update('project_tasks', [
            'assigned_to' => $employeeId,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $taskId]);

        return true;
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

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getProjectAssigneeCandidates(int $projectId): array
    {
        try {
            return $this->connection->fetchAllAssociative(
                "SELECT
                    c.employee_id,
                    CONCAT(e.first_name, ' ', e.last_name) AS username,
                    e.job_title,
                    COALESCE((
                        SELECT COUNT(*)
                        FROM project_tasks t
                        WHERE t.assigned_to = c.employee_id
                          AND t.project_id = c.project_id
                          AND t.status != 'done'
                    ), 0) AS active_project_tasks,
                    COALESCE((
                        SELECT COUNT(*)
                        FROM project_tasks t2
                        WHERE t2.assigned_to = c.employee_id
                          AND t2.status != 'done'
                    ), 0) AS active_total_tasks
                FROM project_collaborators c
                JOIN employees e ON e.id = c.employee_id
                WHERE c.project_id = ? AND c.is_active = 1
                ORDER BY active_project_tasks ASC, active_total_tasks ASC, e.first_name ASC",
                [$projectId]
            );
        } catch (\Throwable) {
            return [];
        }
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

    public function validate(array $data): array
    {
        $data = $this->normalizeTaskInput($data);
        $errors = [];

        if (($data['project_id'] ?? 0) <= 0) {
            $errors['project_id'] = 'Projet invalide.';
            return $errors;
        }

        if ($data['title'] === '') {
            $errors['title'] = 'Le titre de la tache est obligatoire.';
        }

        if ($data['start_date'] === null) {
            $errors['start_date'] = 'La date de debut de la tache est obligatoire.';
        } elseif (!$this->isValidDate($data['start_date'])) {
            $errors['start_date'] = 'La date de debut est invalide (format attendu: AAAA-MM-JJ).';
        }

        if ($data['estimated_hours'] !== null) {
            if (!is_int($data['estimated_hours']) || $data['estimated_hours'] < 0) {
                $errors['estimated_hours'] = 'Les heures estimees doivent etre un entier positif.';
            }
        }

        $project = $this->projectService->getProjectById((int) $data['project_id']);
        if (!$project) {
            $errors['project_id'] = 'Projet introuvable.';
            return $errors;
        }

        $projectEndDate = $project['end_date'] ?? null;
        if (!is_string($projectEndDate) || !$this->isValidDate($projectEndDate)) {
            $errors['project_end_date'] = 'La date de fin du projet est invalide.';
            return $errors;
        }

        if (!isset($errors['start_date']) && $data['start_date'] !== null) {
            $taskStartDate = new \DateTimeImmutable($data['start_date']);
            $projectEnd = new \DateTimeImmutable($projectEndDate);

            if ($taskStartDate > $projectEnd) {
                $errors['start_date'] = 'La date de debut de la tache doit etre avant ou egale a la date de fin du projet.';
            }
        }

        $projectEstimatedHours = $project['estimated_hours'] ?? null;
        if (
            $projectEstimatedHours !== null
            && $projectEstimatedHours !== ''
            && !isset($errors['estimated_hours'])
            && $data['estimated_hours'] !== null
        ) {
            $projectEstimatedHours = (int) $projectEstimatedHours;
            if ($data['estimated_hours'] > $projectEstimatedHours) {
                $errors['estimated_hours'] = sprintf(
                    'Les heures estimees de la tache ne peuvent pas depasser les heures allouees du projet (%d h).',
                    $projectEstimatedHours
                );
            }
        }

        return $errors;
    }

    private function normalizeTaskInput(array $data): array
    {
        $title = isset($data['title']) ? trim((string) $data['title']) : '';

        $description = isset($data['description']) ? trim((string) $data['description']) : null;
        if ($description === '') {
            $description = null;
        }

        $status = isset($data['status']) ? (string) $data['status'] : 'todo';
        $priority = isset($data['priority']) ? (string) $data['priority'] : 'medium';

        $assignedTo = $data['assigned_to'] ?? null;
        if ($assignedTo === '' || $assignedTo === null) {
            $assignedTo = null;
        } elseif (is_numeric($assignedTo)) {
            $assignedTo = (int) $assignedTo;
        }

        $estimatedHours = $data['estimated_hours'] ?? null;
        $estimatedHours = is_string($estimatedHours) ? trim($estimatedHours) : $estimatedHours;
        if ($estimatedHours === '' || $estimatedHours === null) {
            $estimatedHours = 0;
        } elseif (is_numeric($estimatedHours)) {
            $estimatedHours = (int) $estimatedHours;
        }

        $dueDate = $data['due_date'] ?? null;
        $dueDate = is_string($dueDate) ? trim($dueDate) : null;
        if ($dueDate === '') {
            $dueDate = null;
        }

        $startDate = $data['start_date'] ?? null;
        $startDate = is_string($startDate) ? trim($startDate) : null;
        if ($startDate === '') {
            $startDate = null;
        }

        $projectId = isset($data['project_id']) && is_numeric($data['project_id'])
            ? (int) $data['project_id']
            : 0;

        return array_replace($data, [
            'project_id' => $projectId,
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'priority' => $priority,
            'assigned_to' => $assignedTo,
            'estimated_hours' => $estimatedHours,
            'due_date' => $dueDate,
            'start_date' => $startDate,
        ]);
    }

    private function isValidDate(string $value): bool
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $dt instanceof \DateTimeImmutable && $dt->format('Y-m-d') === $value;
    }

    private function supportsTaskStartDate(): bool
    {
        if ($this->hasTaskStartDateColumn !== null) {
            return $this->hasTaskStartDateColumn;
        }

        try {
            $columns = $this->connection->createSchemaManager()->listTableColumns('project_tasks');
            $this->hasTaskStartDateColumn = array_key_exists('start_date', $columns);
        } catch (\Throwable) {
            $this->hasTaskStartDateColumn = false;
        }

        return $this->hasTaskStartDateColumn;
    }
}