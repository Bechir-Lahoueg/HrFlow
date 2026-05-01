<?php

namespace App\Repository\Projet;

use App\Entity\Projet\ProjectMilestone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectMilestone>
 */
class ProjectMilestoneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectMilestone::class);
    }

    public function fetchByProject(int $projectId): array
    {
        return $this->getConnection()->fetchAllAssociative(
            'SELECT * FROM project_milestones
            WHERE project_id = ?
            ORDER BY target_date ASC',
            [$projectId]
        );
    }

    public function fetchById(int $id): ?array
    {
        $milestone = $this->getConnection()->fetchAssociative(
            'SELECT * FROM project_milestones WHERE id = ?',
            [$id]
        );

        return $milestone ?: null;
    }

    public function insertMilestone(array $data): void
    {
        $this->getConnection()->insert('project_milestones', $data);
    }

    public function updateMilestone(int $id, array $data): void
    {
        $this->getConnection()->update('project_milestones', $data, ['id' => $id]);
    }

    public function markCompleted(int $id): void
    {
        $this->getConnection()->update('project_milestones', [
            'status' => 'completed',
            'completion_rate' => 100,
            'completion_date' => date('Y-m-d'),
        ], ['id' => $id]);
    }

    public function deleteMilestone(int $id): void
    {
        $this->getConnection()->delete('project_milestones', ['id' => $id]);
    }

    public function countByProject(int $projectId): int
    {
        return (int) $this->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM project_milestones WHERE project_id = ?',
            [$projectId]
        );
    }

    public function countCompletedByProject(int $projectId): int
    {
        return (int) $this->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM project_milestones
            WHERE project_id = ? AND status = 'completed'",
            [$projectId]
        );
    }

    public function countOverdueByProject(int $projectId): int
    {
        return (int) $this->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM project_milestones
            WHERE project_id = ? AND status != 'completed' AND target_date < CURDATE()",
            [$projectId]
        );
    }

    private function getConnection(): Connection
    {
        return $this->getEntityManager()->getConnection();
    }
}

