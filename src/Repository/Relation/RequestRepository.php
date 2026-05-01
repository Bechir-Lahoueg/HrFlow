<?php

namespace App\Repository\Relation;

use App\Entity\Relation\Request;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Request>
 */
class RequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Request::class);
    }

    public function insert(array $data): void
    {
        $this->getConnection()->insert('requests', $data);
    }

    public function updateRequest(int $id, array $data): void
    {
        $this->getConnection()->update('requests', $data, ['id' => $id]);
    }

    public function updateStatus(int $id, array $data): void
    {
        $this->getConnection()->update('requests', $data, ['id' => $id]);
    }

    public function deleteRequest(int $id): void
    {
        $this->getConnection()->delete('requests', ['id' => $id]);
    }

    public function fetchByUserId(int $employeeId): array
    {
        $sql = "SELECT r.*, rt.name AS type_name,
                CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                u.username AS reviewer_name
                FROM requests r
                LEFT JOIN request_types rt ON r.request_type_id = rt.id
                LEFT JOIN employees e ON r.user_id = e.id
                LEFT JOIN users u ON r.reviewed_by = u.id
                WHERE r.user_id = ?
                ORDER BY r.submitted_date DESC";

        return $this->getConnection()->fetchAllAssociative($sql, [$employeeId]);
    }

    public function fetchByRhId(int $rhId): array
    {
        $sql = "SELECT r.*, rt.name AS type_name,
               CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
               u.username AS reviewer_name
               FROM requests r
               LEFT JOIN request_types rt ON r.request_type_id = rt.id
               INNER JOIN employees e ON r.user_id = e.id
               LEFT JOIN users u ON r.reviewed_by = u.id
               WHERE e.rh_id = ?
               ORDER BY r.submitted_date DESC";

        return $this->getConnection()->fetchAllAssociative($sql, [$rhId]);
    }

    public function fetchById(int $id): ?array
    {
        $row = $this->getConnection()->fetchAssociative(
            "SELECT r.*,
                rt.name AS type_name,
                CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                u.username AS reviewer_name
            FROM requests r
            LEFT JOIN request_types rt ON r.request_type_id = rt.id
            LEFT JOIN employees      e  ON r.user_id         = e.id
            LEFT JOIN users          u  ON r.reviewed_by     = u.id
            WHERE r.id = ?",
            [$id]
        );

        return $row ?: null;
    }

    private function getConnection(): Connection
    {
        return $this->getEntityManager()->getConnection();
    }
}

