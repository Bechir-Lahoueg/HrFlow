<?php

namespace App\Service\Projet;

use App\Repository\Projet\ProjectCollaboratorRepository;
use App\Repository\Projet\ProjectRepository;
use App\Repository\Projet\ProjectTaskRepository;

final class ProjectService
{
    private const ALLOWED_STATUSES = ['planning', 'in_progress', 'on_hold', 'completed', 'cancelled'];
    private const ALLOWED_PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ProjectTaskRepository $taskRepository,
        private readonly ProjectCollaboratorRepository $collaboratorRepository,
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // CRUD PROJETS
    // ═══════════════════════════════════════════════════════════════

    /** @return array<int, array<string, mixed>> */
    public function getAllProjects(): array
    {
        try {
            return $this->projectRepository->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function getProjectsByRh(int $rhId): array
    {
        try {
            return $this->projectRepository->fetchByRh($rhId);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed>|null */
    public function getProjectById(int $id): ?array
    {
        try {
            return $this->projectRepository->fetchById($id);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $data */
    public function createProject(array $data): void
    {
        $data = $this->normalizeProjectInput($data);

        $this->projectRepository->insert([
            'rh_id' => $data['rh_id'],
            'name' => $data['name'],
            'description' => $data['description'],
            'status' => $data['status'],
            'priority' => $data['priority'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'estimated_hours' => $data['estimated_hours'],
            'actual_hours' => 0,
            'budget' => $data['budget'],
            'completion_rate' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string, mixed> $data */
    public function updateProject(int $id, array $data): void
    {
        $data = $this->normalizeProjectInput($data);

        $updateData = [
            'name' => $data['name'],
            'description' => $data['description'],
            'status' => $data['status'],
            'priority' => $data['priority'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'estimated_hours' => $data['estimated_hours'],
            'budget' => $data['budget'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->projectRepository->updateProject($id, $updateData);
    }

    public function deleteProject(int $id): void
    {
        $this->projectRepository->deleteProject($id);
    }

    public function updateCompletionRate(int $projectId): void
    {
        try {
            $total = $this->taskRepository->countByProject($projectId);
            $completed = $this->taskRepository->countByProjectAndStatus($projectId, 'done');

            $completionRate = $total > 0 ? round(($completed / $total) * 100) : 0;

            $this->projectRepository->updateCompletionRate($projectId, (int) $completionRate);
        } catch (\Throwable) {
            // Ignore
        }
    }

    public function updateActualHours(int $projectId): void
    {
        try {
            $actualHours = $this->collaboratorRepository->sumWorkedHoursByProject($projectId);

            $this->projectRepository->updateActualHours($projectId, $actualHours);
        } catch (\Throwable) {
            // Ignore
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // STATISTIQUES
    // ═══════════════════════════════════════════════════════════════

    /** @return array<string, int|float> */
    public function getProjectStats(int $rhId): array
    {
        try {
            $total = $this->projectRepository->countByRh($rhId);
            $active = $this->projectRepository->countByRhAndStatus($rhId, 'in_progress');
            $completed = $this->projectRepository->countByRhAndStatus($rhId, 'completed');
            $onHold = $this->projectRepository->countByRhAndStatus($rhId, 'on_hold');
            $totalTasks = $this->projectRepository->countTasksByRh($rhId);
            $completedTasks = $this->projectRepository->countTasksByRhAndStatus($rhId, 'done');

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

    /** @return array<int, array<string, mixed>> */
    public function getProjectsWithDetails(int $rhId): array
    {
        try {
            $projects = $this->getProjectsByRh($rhId);

            foreach ($projects as &$project) {
                $projectId = (int) ($project['id'] ?? 0);
                if ($projectId <= 0) {
                    continue;
                }

                $project['team_count'] = $this->collaboratorRepository->countActiveByProject($projectId);
                $project['tasks_count'] = $this->taskRepository->countByProject($projectId);
                $project['tasks_completed'] = $this->taskRepository->countByProjectAndStatus($projectId, 'done');

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
        return match ($status) {
            'planning' => 'Planification',
            'in_progress' => 'En cours',
            'on_hold' => 'En pause',
            'completed' => 'Terminé',
            'cancelled' => 'Annulé',

            // compat éventuelle (anciens statuts)
            'pending' => 'En attente',
            'active' => 'Actif',

            default => $status,
        };
    }

    public function getPriorityLabel(string $priority): string
    {
        return match ($priority) {
            'low' => 'Faible',
            'medium' => 'Moyenne',
            'high' => 'Haute',
            'critical' => 'Critique',
            default => $priority,
        };
    }

    public function getStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'planning' => 'badge bg-secondary',
            'in_progress' => 'badge bg-success',
            'on_hold' => 'badge bg-warning',
            'completed' => 'badge bg-primary',
            'cancelled' => 'badge bg-danger',

            // compat éventuelle (anciens statuts)
            'pending' => 'badge bg-secondary',
            'active' => 'badge bg-primary',

            default => 'badge bg-secondary',
        };
    }

    public function getPriorityBadgeClass(string $priority): string
    {
        return match ($priority) {
            'low' => 'badge bg-info',
            'medium' => 'badge bg-warning',
            'high' => 'badge bg-danger',
            'critical' => 'badge bg-danger',
            default => 'badge bg-secondary',
        };
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $data = $this->normalizeProjectInput($data);
        $errors = [];

        // Nom
        if ($data['name'] === '' || mb_strlen($data['name']) < 3) {
            $errors['name'] = 'Le nom du projet doit contenir au moins 3 caractères.';
        } elseif (mb_strlen($data['name']) > 255) {
            $errors['name'] = 'Le nom du projet ne doit pas dépasser 255 caractères.';
        }

        // Statut / priorité (obligatoires en DB)
        if ($data['status'] === '' || !in_array($data['status'], self::ALLOWED_STATUSES, true)) {
            $errors['status'] = 'Statut invalide.';
        }
        if ($data['priority'] === '' || !in_array($data['priority'], self::ALLOWED_PRIORITIES, true)) {
            $errors['priority'] = 'Priorité invalide.';
        }

        // Dates
        if ($data['start_date'] === null) {
            $errors['start_date'] = 'La date de début est obligatoire.';
        } elseif (!$this->isValidDate($data['start_date'])) {
            $errors['start_date'] = 'Format de date invalide (attendu: AAAA-MM-JJ).';
        }

        if ($data['end_date'] === null) {
            $errors['end_date'] = 'La date de fin est obligatoire.';
        } elseif (!$this->isValidDate($data['end_date'])) {
            $errors['end_date'] = 'Format de date invalide (attendu: AAAA-MM-JJ).';
        }

        if (
            !isset($errors['start_date'])
            && !isset($errors['end_date'])
            && $data['start_date'] !== null
            && $data['end_date'] !== null
        ) {
            $start = new \DateTimeImmutable($data['start_date']);
            $end = new \DateTimeImmutable($data['end_date']);

            if ($end < $start) {
                $errors['end_date'] = 'La date de fin ne peut pas être antérieure à la date de début.';
            }
        }

        // Heures estimées (optionnel)
        if ($data['estimated_hours'] !== null) {
            if (!is_int($data['estimated_hours']) || $data['estimated_hours'] < 0) {
                $errors['estimated_hours'] = "Le nombre d'heures doit être un entier positif.";
            }
        }

        // Budget (optionnel)
        if ($data['budget'] !== null) {
            if (!is_numeric($data['budget']) || (float) $data['budget'] < 0) {
                $errors['budget'] = 'Le budget doit être un montant positif.';
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeProjectInput(array $data): array
    {
        $name = isset($data['name']) ? trim((string) $data['name']) : '';

        $description = isset($data['description']) ? trim((string) $data['description']) : null;
        if ($description === '') {
            $description = null;
        }

        $status = isset($data['status']) ? (string) $data['status'] : 'planning';
        $priority = isset($data['priority']) ? (string) $data['priority'] : 'medium';

        $startDate = $data['start_date'] ?? null;
        $startDate = is_string($startDate) ? trim($startDate) : null;
        if ($startDate === '') {
            $startDate = null;
        }

        $endDate = $data['end_date'] ?? null;
        $endDate = is_string($endDate) ? trim($endDate) : null;
        if ($endDate === '') {
            $endDate = null;
        }

        $estimated = $data['estimated_hours'] ?? null;
        $estimated = is_string($estimated) ? trim($estimated) : $estimated;
        if ($estimated === '' || $estimated === null) {
            $estimated = null;
        } elseif (is_numeric($estimated)) {
            $estimated = (int) $estimated;
        }

        $budget = $data['budget'] ?? null;
        $budget = is_string($budget) ? trim($budget) : $budget;
        if ($budget === '' || $budget === null) {
            $budget = null;
        } elseif (is_numeric($budget)) {
            $budget = (float) $budget;
        }

        return array_replace($data, [
            'name' => $name,
            'description' => $description,
            'status' => $status,
            'priority' => $priority,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'estimated_hours' => $estimated,
            'budget' => $budget,
        ]);
    }

    private function isValidDate(string $value): bool
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $dt instanceof \DateTimeImmutable && $dt->format('Y-m-d') === $value;
    }
}

