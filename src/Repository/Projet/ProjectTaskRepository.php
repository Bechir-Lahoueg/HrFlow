<?php

namespace App\Repository\Projet;

use App\Entity\Projet\ProjectTask;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectTask>
 */
class ProjectTaskRepository extends ServiceEntityRepository
{
    private ?bool $hasTaskStartDateColumn = null;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectTask::class);
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchByProject(int $projectId): array
    {
        return $this->getConnection()->fetchAllAssociative(
            "SELECT t.*, CONCAT(e.first_name, ' ', e.last_name) as assigned_to_name
            FROM project_tasks t
            LEFT JOIN employees e ON t.assigned_to = e.id
            WHERE t.project_id = ?
            ORDER BY t.order_index ASC, t.created_at DESC",
            [$projectId]
        );
    }

    /** @return array<string, mixed>|null */
    public function fetchById(int $id): ?array
    {
        $task = $this->getConnection()->fetchAssociative(
            "SELECT t.*, CONCAT(e.first_name, ' ', e.last_name) as assigned_to_name
            FROM project_tasks t
            LEFT JOIN employees e ON t.assigned_to = e.id
            WHERE t.id = ?",
            [$id]
        );

        return $task ?: null;
    }

    public function getMaxOrderIndex(int $projectId): int
    {
        return (int) $this->getConnection()->fetchOne(
            'SELECT COALESCE(MAX(order_index), 0) FROM project_tasks WHERE project_id = ?',
            [$projectId]
        );
    }

    /** @param array<string, mixed> $data */
    public function insertTask(array $data): void
    {
        $this->getConnection()->insert('project_tasks', $data);
    }

    /** @param array<string, mixed> $data */
    public function updateTask(int $taskId, array $data): void
    {
        $this->getConnection()->update('project_tasks', $data, ['id' => $taskId]);
    }

    public function updateTaskOrder(int $taskId, int $newOrder): void
    {
        $this->getConnection()->update('project_tasks', [
            'order_index' => $newOrder,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $taskId]);
    }

    public function deleteTask(int $taskId): void
    {
        $this->getConnection()->delete('project_tasks', ['id' => $taskId]);
    }

    public function assignTask(int $taskId, int $employeeId): void
    {
        $this->getConnection()->update('project_tasks', [
            'assigned_to' => $employeeId,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $taskId]);
    }

    public function incrementActualHours(int $taskId, int $hours): void
    {
        $this->getConnection()->executeStatement(
            'UPDATE project_tasks SET actual_hours = actual_hours + ?, updated_at = ? WHERE id = ?',
            [$hours, date('Y-m-d H:i:s'), $taskId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchByEmployee(int $employeeId): array
    {
        return $this->getConnection()->fetchAllAssociative(
            'SELECT t.*, p.name as project_name, p.status as project_status
            FROM project_tasks t
            JOIN projects p ON t.project_id = p.id
            WHERE t.assigned_to = ?
            ORDER BY t.due_date ASC, t.created_at DESC',
            [$employeeId]
        );
    }

    public function countByEmployee(int $employeeId): int
    {
        return (int) $this->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM project_tasks WHERE assigned_to = ?',
            [$employeeId]
        );
    }

    public function countByEmployeeAndStatus(int $employeeId, string $status): int
    {
        return (int) $this->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM project_tasks WHERE assigned_to = ? AND status = ?',
            [$employeeId, $status]
        );
    }

    public function countOverdueByEmployee(int $employeeId): int
    {
        return (int) $this->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM project_tasks
            WHERE assigned_to = ? AND status != 'done' AND due_date < CURDATE()",
            [$employeeId]
        );
    }

    public function countByProject(int $projectId): int
    {
        return (int) $this->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM project_tasks WHERE project_id = ?',
            [$projectId]
        );
    }

    public function countByProjectAndStatus(int $projectId, string $status): int
    {
        return (int) $this->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM project_tasks WHERE project_id = ? AND status = ?',
            [$projectId, $status]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function getProjectAssigneeCandidates(int $projectId): array
    {
        return $this->getConnection()->fetchAllAssociative(
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
    }

    public function supportsTaskStartDate(): bool
    {
        if ($this->hasTaskStartDateColumn !== null) {
            return $this->hasTaskStartDateColumn;
        }

        try {
            $columns = $this->getConnection()->createSchemaManager()->listTableColumns('project_tasks');
            $this->hasTaskStartDateColumn = array_key_exists('start_date', $columns);
        } catch (\Throwable) {
            $this->hasTaskStartDateColumn = false;
        }

        return $this->hasTaskStartDateColumn;
    }

    private function getConnection(): Connection
    {
        return $this->getEntityManager()->getConnection();
    }
}

