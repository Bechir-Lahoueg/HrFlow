<?php

namespace App\Repository\Projet;

use App\Entity\Projet\ProjectCollaborator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectCollaborator>
 */
class ProjectCollaboratorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectCollaborator::class);
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchByProject(int $projectId): array
    {
        return $this->getConnection()->fetchAllAssociative(
            "SELECT c.*, CONCAT(e.first_name, ' ', e.last_name) AS username, e.email, e.job_title
            FROM project_collaborators c
            JOIN employees e ON c.employee_id = e.id
            WHERE c.project_id = ? AND c.is_active = 1
            ORDER BY c.joined_date DESC",
            [$projectId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchProjectsByEmployee(int $employeeId): array
    {
        return $this->getConnection()->fetchAllAssociative(
            'SELECT p.*, c.role, c.worked_hours, c.assigned_hours
            FROM projects p
            JOIN project_collaborators c ON p.id = c.project_id
            WHERE c.employee_id = ? AND c.is_active = 1
            ORDER BY p.created_at DESC',
            [$employeeId]
        );
    }

    /** @param array<string, mixed> $data */
    public function insertCollaborator(array $data): void
    {
        $this->getConnection()->insert('project_collaborators', $data);
    }

    /** @param array<string, mixed> $data */
    public function updateCollaborator(int $id, array $data): void
    {
        $this->getConnection()->update('project_collaborators', $data, ['id' => $id]);
    }

    public function deactivateCollaborator(int $projectId, int $employeeId, string $leftDate): void
    {
        $this->getConnection()->executeStatement(
            'UPDATE project_collaborators
             SET is_active = 0, left_date = ?
             WHERE project_id = ? AND employee_id = ?',
            [$leftDate, $projectId, $employeeId]
        );
    }

    public function releaseProjectTasks(int $projectId, int $employeeId): void
    {
        $this->getConnection()->executeStatement(
            'UPDATE project_tasks SET assigned_to = NULL
             WHERE project_id = ? AND assigned_to = ?',
            [$projectId, $employeeId]
        );
    }

    /** @return array<string, mixed>|null */
    public function fetchCollaboratorById(int $id): ?array
    {
        $row = $this->getConnection()->fetchAssociative(
            'SELECT * FROM project_collaborators WHERE id = ?',
            [$id]
        );

        return $row ?: null;
    }

    public function isCollaborator(int $projectId, int $employeeId): bool
    {
        $result = $this->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM project_collaborators
             WHERE project_id = ? AND employee_id = ? AND is_active = 1',
            [$projectId, $employeeId]
        );

        return (int) $result > 0;
    }

    public function getCollaboratorRole(int $projectId, int $employeeId): ?string
    {
        $role = $this->getConnection()->fetchOne(
            'SELECT role FROM project_collaborators
             WHERE project_id = ? AND employee_id = ? AND is_active = 1',
            [$projectId, $employeeId]
        );

        return is_string($role) ? $role : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function getAvailableEmployees(int $projectId, int $rhId): array
    {
        return $this->getConnection()->fetchAllAssociative(
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

    public function sumAssignedHoursByProject(int $projectId): int
    {
        return (int) $this->getConnection()->fetchOne(
            'SELECT COALESCE(SUM(assigned_hours), 0)
             FROM project_collaborators
             WHERE project_id = ? AND is_active = 1',
            [$projectId]
        );
    }

    public function sumWorkedHoursByProject(int $projectId): int
    {
        return (int) $this->getConnection()->fetchOne(
            'SELECT COALESCE(SUM(worked_hours), 0)
             FROM project_collaborators
             WHERE project_id = ? AND is_active = 1',
            [$projectId]
        );
    }

    public function fetchEmployeeJobTitle(int $employeeId): ?string
    {
        $jobTitle = $this->getConnection()->fetchOne(
            'SELECT job_title FROM employees WHERE id = ?',
            [$employeeId]
        );

        return is_string($jobTitle) ? $jobTitle : null;
    }

    public function collaboratorExists(int $projectId, int $employeeId): bool
    {
        $exists = $this->getConnection()->fetchOne(
            'SELECT id FROM project_collaborators
             WHERE project_id = ? AND employee_id = ? AND is_active = 1',
            [$projectId, $employeeId]
        );

        return (bool) $exists;
    }

    public function countActiveByProject(int $projectId): int
    {
        return (int) $this->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM project_collaborators WHERE project_id = ? AND is_active = 1',
            [$projectId]
        );
    }

    public function incrementWorkedHours(int $collaboratorId, int $hours): void
    {
        $this->getConnection()->executeStatement(
            'UPDATE project_collaborators SET worked_hours = worked_hours + ? WHERE id = ?',
            [$hours, $collaboratorId]
        );
    }

    private function getConnection(): Connection
    {
        return $this->getEntityManager()->getConnection();
    }
}

