<?php

namespace App\Service\Projet;

use Doctrine\DBAL\Connection;

final class ProjectCollaboratorService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ProjectService $projectService
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // CRUD COLLABORATEURS
    // ═══════════════════════════════════════════════════════════════

    public function getCollaboratorsByProject(int $projectId): array
    {
       return $this->connection->fetchAllAssociative(
                   "SELECT c.*, CONCAT(e.first_name, ' ', e.last_name) AS username, e.email, e.job_title
                   FROM project_collaborators c
                   JOIN employees e ON c.employee_id = e.id
                   WHERE c.project_id = ? AND c.is_active = 1
                   ORDER BY c.joined_date DESC",
                   [$projectId]
               );
           }

    public function getProjectsByEmployee(int $employeeId): array
    {
        try {
            return $this->connection->fetchAllAssociative(
                'SELECT p.*, c.role, c.worked_hours, c.assigned_hours
                FROM projects p
                JOIN project_collaborators c ON p.id = c.project_id
                WHERE c.employee_id = ? AND c.is_active = 1
                ORDER BY p.created_at DESC',
                [$employeeId]
            );
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

           $this->connection->insert('project_collaborators', [
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
        $this->connection->update('project_collaborators', [
            'role' => $data['role'],
            'assigned_hours' => $data['assigned_hours'] ?? 0,
        ], ['id' => $id]);
    }

    public function removeCollaborator(int $projectId, int $employeeId): void
        {
            // On désactive le collaborateur
            $this->connection->executeStatement(
                'UPDATE project_collaborators
                 SET is_active = 0, left_date = ?
                 WHERE project_id = ? AND employee_id = ?',
                [date('Y-m-d'), $projectId, $employeeId]
            );

            // On libère ses tâches sur ce projet
            $this->connection->executeStatement(
                'UPDATE project_tasks SET assigned_to = NULL
                 WHERE project_id = ? AND assigned_to = ?',
                [$projectId, $employeeId]
            );
        }

    public function logWorkedHours(int $collaboratorId, int $hours): void
    {
        $collab = $this->connection->fetchAssociative(
            'SELECT * FROM project_collaborators WHERE id = ?',
            [$collaboratorId]
        );

        if (!$collab) {
            return;
        }

        $newWorkedHours = (int)$collab['worked_hours'] + $hours;

        $this->connection->update('project_collaborators', [
            'worked_hours' => $newWorkedHours,
        ], ['id' => $collaboratorId]);

        // Mettre à jour les heures du projet
        $this->projectService->updateActualHours($collab['project_id']);
    }

    // ═══════════════════════════════════════════════════════════════
    // QUERIES UTILES
    // ═══════════════════════════════════════════════════════════════

    public function isCollaborator(int $projectId, int $employeeId): bool
    {
        try {
            $result = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM project_collaborators
                WHERE project_id = ? AND employee_id = ? AND is_active = 1',
                [$projectId, $employeeId]
            );

            return (int)$result > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getCollaboratorRole(int $projectId, int $employeeId): ?string
    {
        try {
            return $this->connection->fetchOne(
                'SELECT role FROM project_collaborators
                WHERE project_id = ? AND employee_id = ? AND is_active = 1',
                [$projectId, $employeeId]
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public function getAvailableEmployees(int $projectId, int $rhId): array
        {

            return $this->connection->fetchAllAssociative(
                "SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) AS username, e.email, e.job_title
                FROM employees e
                WHERE e.rh_id = :rhId
                AND e.id NOT IN (
                    SELECT employee_id FROM project_collaborators
                    WHERE project_id = :projectId AND is_active = 1
                )
                ORDER BY e.first_name ASC",
                ['rhId' => $rhId, 'projectId' => $projectId]
            );
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
        $alreadyAssigned = (int) $this->connection->fetchOne(
            'SELECT COALESCE(SUM(assigned_hours), 0)
             FROM project_collaborators
             WHERE project_id = ? AND is_active = 1',
            [$projectId]
        );

        return max(0, $estimatedHours - $alreadyAssigned);
    }

    private function resolveRoleFromEmployeeJobTitle(int $employeeId): string
    {
        $jobTitle = $this->connection->fetchOne(
            'SELECT job_title FROM employees WHERE id = ?',
            [$employeeId]
        );

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
            $exists = $this->connection->fetchOne(
                'SELECT id FROM project_collaborators
                 WHERE project_id = ? AND employee_id = ? AND is_active = 1',
                [(int) $data['project_id'], (int) $data['employee_id']]
            );

            if ($exists) {
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