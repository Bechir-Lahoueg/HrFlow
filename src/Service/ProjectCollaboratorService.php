<?php

namespace App\Service;

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

   public function addCollaborator(array $data): void
       {
           // Vérifier si déjà collaborateur actif
           $exists = $this->connection->fetchOne(
               'SELECT id FROM project_collaborators
               WHERE project_id = ? AND employee_id = ? AND is_active = 1',
               [$data['project_id'], $data['employee_id']]
           );

           if ($exists) {
               return;
           }

           $this->connection->insert('project_collaborators', [
               'project_id'     => $data['project_id'],
               'employee_id'    => $data['employee_id'],
               'role'           => $data['role'] ?? 'Membre',
               'assigned_hours' => $data['assigned_hours'] ?? 0,
               'worked_hours'   => 0,
               'joined_date'    => date('Y-m-d'),
               'is_active'      => 1,
               'created_at'     => date('Y-m-d H:i:s'),
           ]);
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
}