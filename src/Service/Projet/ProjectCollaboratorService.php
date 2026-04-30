<?php

namespace App\Service\Projet;

use App\Repository\Projet\ProjectCollaboratorRepository;

final class ProjectCollaboratorService
{
    public function __construct(
        private readonly ProjectCollaboratorRepository $collaboratorRepository,
        private readonly ProjectService $projectService
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // CRUD COLLABORATEURS
    // ═══════════════════════════════════════════════════════════════

    public function getCollaboratorsByProject(int $projectId): array
    {
        return $this->collaboratorRepository->fetchByProject($projectId);
    }

    public function getProjectsByEmployee(int $employeeId): array
    {
        try {
            return $this->collaboratorRepository->fetchProjectsByEmployee($employeeId);
        } catch (\Throwable) {
            return [];
        }
    }

    public function addCollaborator(array $data): array
    {
        $data = $this->normalizeAddCollaboratorInput($data);
        $errors = $this->validateAddCollaboratorInput($data);

        if ($errors !== []) {
            return [
                'success' => false,
                'message' => 'Impossible d\'ajouter ce membre.',
                'errors' => $errors,
                'data' => $data,
            ];
        }

        $role = $this->resolveRoleFromEmployeeJobTitle((int) $data['employee_id']);

        $this->collaboratorRepository->insertCollaborator([
            'project_id'     => (int) $data['project_id'],
            'employee_id'    => (int) $data['employee_id'],
            'role'           => $role,
            'assigned_hours' => (int) $data['assigned_hours'],
            'worked_hours'   => 0,
            'joined_date'    => date('Y-m-d'),
            'is_active'      => 1,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'message' => 'Membre ajoute avec succes.'];
    }

    public function updateCollaborator(int $id, array $data): void
    {
        $this->collaboratorRepository->updateCollaborator($id, [
            'role' => $data['role'],
            'assigned_hours' => $data['assigned_hours'] ?? 0,
        ]);
    }

    public function removeCollaborator(int $projectId, int $employeeId): void
    {
        $this->collaboratorRepository->deactivateCollaborator($projectId, $employeeId, date('Y-m-d'));
        $this->collaboratorRepository->releaseProjectTasks($projectId, $employeeId);
    }

    public function logWorkedHours(int $collaboratorId, int $hours): void
    {
        $collab = $this->collaboratorRepository->fetchCollaboratorById($collaboratorId);

        if (!$collab) {
            return;
        }

        $this->collaboratorRepository->incrementWorkedHours($collaboratorId, $hours);

        $this->projectService->updateActualHours($collab['project_id']);
    }

    // ═══════════════════════════════════════════════════════════════
    // QUERIES UTILES
    // ═══════════════════════════════════════════════════════════════

    public function isCollaborator(int $projectId, int $employeeId): bool
    {
        try {
            return $this->collaboratorRepository->isCollaborator($projectId, $employeeId);
        } catch (\Throwable) {
            return false;
        }
    }

    public function getCollaboratorRole(int $projectId, int $employeeId): ?string
    {
        try {
            return $this->collaboratorRepository->getCollaboratorRole($projectId, $employeeId);
        } catch (\Throwable) {
            return null;
        }
    }

    public function getAvailableEmployees(int $projectId, int $rhId): array
    {
        return $this->collaboratorRepository->getAvailableEmployees($projectId, $rhId);
    }

    public function getRemainingAssignableHours(int $projectId): ?int
    {
        $project = $this->projectService->getProjectById($projectId);
        if (!$project) {
            return null;
        }

        $estimatedHours = $project['estimated_hours'] ?? null;
        if ($estimatedHours === null || $estimatedHours === '') {
            return null;
        }

        $estimatedHours = (int) $estimatedHours;
        $alreadyAssigned = $this->collaboratorRepository->sumAssignedHoursByProject($projectId);

        return max(0, $estimatedHours - $alreadyAssigned);
    }

    private function resolveRoleFromEmployeeJobTitle(int $employeeId): string
    {
        $jobTitle = $this->collaboratorRepository->fetchEmployeeJobTitle($employeeId);

        if (!is_string($jobTitle) || trim($jobTitle) === '') {
            return 'Membre';
        }

        return trim($jobTitle);
    }

    private function normalizeAddCollaboratorInput(array $data): array
    {
        $projectId = isset($data['project_id']) && is_numeric($data['project_id'])
            ? (int) $data['project_id']
            : 0;

        $employeeId = isset($data['employee_id']) && is_numeric($data['employee_id'])
            ? (int) $data['employee_id']
            : 0;

        $assignedHours = $data['assigned_hours'] ?? 0;
        $assignedHours = is_string($assignedHours) ? trim($assignedHours) : $assignedHours;
        if ($assignedHours === '' || $assignedHours === null) {
            $assignedHours = 0;
        }

        return array_replace($data, [
            'project_id' => $projectId,
            'employee_id' => $employeeId,
            'assigned_hours' => $assignedHours,
        ]);
    }

    private function validateAddCollaboratorInput(array $data): array
    {
        $errors = [];

        if (($data['project_id'] ?? 0) <= 0) {
            $errors['project_id'] = 'Projet invalide.';
            return $errors;
        }

        if (($data['employee_id'] ?? 0) <= 0) {
            $errors['employee_id'] = 'Veuillez selectionner un employe.';
        }

        if (!is_numeric($data['assigned_hours'])) {
            $errors['assigned_hours'] = 'Le nombre d\'heures allouees est invalide.';
        } else {
            $assignedHours = (int) $data['assigned_hours'];
            if ($assignedHours < 0) {
                $errors['assigned_hours'] = 'Le nombre d\'heures allouees ne peut pas etre negatif.';
            }
        }

        if (!isset($errors['employee_id'])) {
            if ($this->collaboratorRepository->collaboratorExists((int) $data['project_id'], (int) $data['employee_id'])) {
                $errors['employee_id'] = 'Cet employe est deja membre actif du projet.';
            }
        }

        if (!isset($errors['assigned_hours'])) {
            $remainingHours = $this->getRemainingAssignableHours((int) $data['project_id']);
            if ($remainingHours !== null && (int) $data['assigned_hours'] > $remainingHours) {
                $errors['assigned_hours'] = sprintf(
                    'Heures insuffisantes: il reste %d h allouables sur ce projet.',
                    $remainingHours
                );
            }
        }

        return $errors;
    }
}

