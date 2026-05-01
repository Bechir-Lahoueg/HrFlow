<?php

namespace App\Service\Projet;

use App\Repository\Projet\ProjectTaskRepository;

final class ProjectTaskService
{
    public function __construct(
        private readonly ProjectTaskRepository $taskRepository,
        private readonly ProjectService $projectService
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // CRUD TÂCHES
    // ═══════════════════════════════════════════════════════════════

    public function getTasksByProject(int $projectId): array
    {
        try {
            return $this->taskRepository->fetchByProject($projectId);
        } catch (\Throwable) {
            return [];
        }
    }

    public function getTaskById(int $id): ?array
    {
        try {
            return $this->taskRepository->fetchById($id);
        } catch (\Throwable) {
            return null;
        }
    }

    public function createTask(array $data): void
    {
        $data = $this->normalizeTaskInput($data);

        $maxOrder = $this->taskRepository->getMaxOrderIndex($data['project_id']);

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
            'order_index' => (int) $maxOrder + 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($this->supportsTaskStartDate()) {
            $insertData['start_date'] = $data['start_date'] ?? null;
        }

        $this->taskRepository->insertTask($insertData);

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

        $this->taskRepository->updateTask($id, $updateData);

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

        $this->taskRepository->updateTask($id, $updateData);

        // Mettre à jour le taux d'avancement du projet
        $this->projectService->updateCompletionRate($task['project_id']);
    }

    public function assignTaskToEmployee(int $taskId, int $employeeId): bool
    {
        $task = $this->getTaskById($taskId);
        if (!$task) {
            return false;
        }

        $this->taskRepository->assignTask($taskId, $employeeId);

        return true;
    }

    public function deleteTask(int $id): void
    {
        $task = $this->getTaskById($id);
        if (!$task) {
            return;
        }

        $projectId = $task['project_id'];

        $this->taskRepository->deleteTask($id);

        // Mettre à jour le taux d'avancement du projet
        $this->projectService->updateCompletionRate($projectId);
    }

    public function logHours(int $taskId, int $hours): void
    {
        $task = $this->getTaskById($taskId);
        if (!$task) {
            return;
        }

        $this->taskRepository->incrementActualHours($taskId, $hours);
    }

    // ═══════════════════════════════════════════════════════════════
    // KANBAN - DRAG & DROP
    // ═══════════════════════════════════════════════════════════════

    public function updateTaskOrder(int $taskId, int $newOrder): void
    {
        $this->taskRepository->updateTaskOrder($taskId, $newOrder);
    }

    public function getProjectAssigneeCandidates(int $projectId): array
    {
        try {
            return $this->taskRepository->getProjectAssigneeCandidates($projectId);
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
            return $this->taskRepository->fetchByEmployee($employeeId);
        } catch (\Throwable) {
            return [];
        }
    }

    public function getEmployeeTaskStats(int $employeeId): array
    {
        try {
            $total = $this->taskRepository->countByEmployee($employeeId);
            $completed = $this->taskRepository->countByEmployeeAndStatus($employeeId, 'done');
            $inProgress = $this->taskRepository->countByEmployeeAndStatus($employeeId, 'in_progress');
            $overdue = $this->taskRepository->countOverdueByEmployee($employeeId);

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
        return $this->taskRepository->supportsTaskStartDate();
    }
}

