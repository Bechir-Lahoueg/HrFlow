<?php

namespace App\Repository\Relation;

use App\Entity\Relation\Feedback;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Feedback>
 */
class FeedbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Feedback::class);
    }

    public function insert(array $data): void
    {
        $this->getConnection()->insert('feedbacks', $data);
    }

    public function updateFeedback(int $id, array $data): void
    {
        $this->getConnection()->update('feedbacks', $data, ['id' => $id]);
    }

    public function acknowledge(int $id): void
    {
        $this->getConnection()->update('feedbacks', ['status' => 'acknowledged'], ['id' => $id]);
    }

    public function deleteFeedback(int $id): void
    {
        $this->getConnection()->delete('feedbacks', ['id' => $id]);
    }

    public function fetchAll(): array
    {
        return $this->getConnection()->fetchAllAssociative(
            "SELECT f.*,
                CONCAT(e1.first_name, ' ', e1.last_name) AS from_username,
                CONCAT(e2.first_name, ' ', e2.last_name) AS to_username
            FROM feedbacks f
            INNER JOIN employees e1 ON f.from_user_id = e1.id
            INNER JOIN employees e2 ON f.to_user_id   = e2.id
            ORDER BY f.created_at DESC"
        );
    }

    public function fetchByRhId(int $rhId): array
    {
        return $this->getConnection()->fetchAllAssociative(
            "SELECT f.*,
                CONCAT(e1.first_name, ' ', e1.last_name) AS from_username,
                CONCAT(e2.first_name, ' ', e2.last_name) AS to_username
            FROM feedbacks f
            INNER JOIN employees e1 ON f.from_user_id = e1.id
            INNER JOIN employees e2 ON f.to_user_id   = e2.id
            WHERE e1.rh_id = ? OR e2.rh_id = ?
            ORDER BY f.created_at DESC",
            [$rhId, $rhId]
        );
    }

    public function fetchReceivedByEmployee(int $employeeId): array
    {
        return $this->getConnection()->fetchAllAssociative(
            "SELECT f.*,
                CONCAT(e1.first_name, ' ', e1.last_name) AS from_username,
                CONCAT(e2.first_name, ' ', e2.last_name) AS to_username
            FROM feedbacks f
            LEFT JOIN employees e1 ON f.from_user_id = e1.id
            LEFT JOIN employees e2 ON f.to_user_id   = e2.id
            WHERE f.to_user_id = ?
            ORDER BY f.created_at DESC",
            [$employeeId]
        );
    }

    public function fetchSentByEmployee(int $employeeId): array
    {
        return $this->getConnection()->fetchAllAssociative(
            "SELECT f.*,
                CONCAT(e1.first_name, ' ', e1.last_name) AS from_username,
                CONCAT(e2.first_name, ' ', e2.last_name) AS to_username
            FROM feedbacks f
            LEFT JOIN employees e1 ON f.from_user_id = e1.id
            LEFT JOIN employees e2 ON f.to_user_id   = e2.id
            WHERE f.from_user_id = ?
            ORDER BY f.created_at DESC",
            [$employeeId]
        );
    }

    public function fetchById(int $id): ?array
    {
        $row = $this->getConnection()->fetchAssociative(
            "SELECT f.*,
                CONCAT(e1.first_name, ' ', e1.last_name) AS from_username,
                CONCAT(e2.first_name, ' ', e2.last_name) AS to_username
            FROM feedbacks f
            LEFT JOIN employees e1 ON f.from_user_id = e1.id
            LEFT JOIN employees e2 ON f.to_user_id   = e2.id
            WHERE f.id = ?",
            [$id]
        );

        return $row ?: null;
    }

    public function fetchColleagues(int $employeeId): array
    {
        return $this->getConnection()->fetchAllAssociative(
            "SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) AS fullname
            FROM employees e
            WHERE e.rh_id = (SELECT rh_id FROM employees WHERE id = ?)
            AND e.id != ?
            ORDER BY e.first_name",
            [$employeeId, $employeeId]
        );
    }

    private function getConnection(): Connection
    {
        return $this->getEntityManager()->getConnection();
    }
}

