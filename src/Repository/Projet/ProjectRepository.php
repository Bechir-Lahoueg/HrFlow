<?php

namespace App\Repository\Projet;

use App\Entity\Projet\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function fetchAll(): array
    {
        return $this->getConnection()->fetchAllAssociative(
            'SELECT * FROM projects ORDER BY created_at DESC'
        );
    }

    public function fetchByRh(int $rhId): array
    {
        return $this->getConnection()->fetchAllAssociative(
            'SELECT * FROM projects WHERE rh_id = ? ORDER BY created_at DESC',
            [$rhId]
        );
    }

    public function fetchById(int $id): ?array
    {
        $project = $this->getConnection()->fetchAssociative(
            'SELECT * FROM projects WHERE id = ?',
            [$id]
        );

        return $project ?: null;
    }

    public function insert(array $data): void
    {
        $this->getConnection()->insert('projects', $data);
    }

    public function updateProject(int $id, array $data): void
    {
        $this->getConnection()->update('projects', $data, ['id' => $id]);
    }

    public function deleteProject(int $id): void
    {
        $this->getConnection()->delete('projects', ['id' => $id]);
    }

    public function updateCompletionRate(int $projectId, int $completionRate): void
    {
        $this->getConnection()->update('projects', [
            'completion_rate' => $completionRate,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $projectId]);
    }

    public function updateActualHours(int $projectId, int $actualHours): void
    {
        $this->getConnection()->update('projects', [
            'actual_hours' => $actualHours,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $projectId]);
    }

    public function countByRh(int $rhId): int
    {
        return (int) $this->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM projects WHERE rh_id = ?',
            [$rhId]
        );
    }

    public function countByRhAndStatus(int $rhId, string $status): int
    {
        return (int) $this->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM projects WHERE rh_id = ? AND status = ?',
            [$rhId, $status]
        );
    }

    public function countTasksByRh(int $rhId): int
    {
        return (int) $this->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM project_tasks pt
            JOIN projects p ON pt.project_id = p.id
            WHERE p.rh_id = ?',
            [$rhId]
        );
    }

    public function countTasksByRhAndStatus(int $rhId, string $status): int
    {
        return (int) $this->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM project_tasks pt
            JOIN projects p ON pt.project_id = p.id
            WHERE p.rh_id = ? AND pt.status = ?',
            [$rhId, $status]
        );
    }

    private function getConnection(): Connection
    {
        return $this->getEntityManager()->getConnection();
    }
}

