<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class RequestService
{
    private const ALLOWED_PRIORITIES = ['low', 'medium', 'high'];
    private const MAX_TITLE_LENGTH = 255;
    private const MAX_DESCRIPTION_LENGTH = 2000;

    public function __construct(private readonly Connection $connection) {}

    // ═══════════════════════════════════════════════════════════════
    // CREATE
    // ═══════════════════════════════════════════════════════════════

    public function add(array $data): bool
    {
        try {
            $data = $this->normalizeRequestInput($data);
            $this->connection->insert('requests', [
                'user_id'         => $data['user_id'],
                'request_type_id' => $data['request_type_id'],
                'title'           => $data['title'],
                'description'     => $data['description'],
                'attachment_url'  => $data['attachment_url'] ?? null,
                'status'          => 'pending',
                'priority'        => $data['priority'],
                'submitted_date'  => date('Y-m-d H:i:s'),
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // READ
    // ═══════════════════════════════════════════════════════════════

    public function getByUserId(int $employeeId): array
    {
        // Note : $employeeId doit être l'ID de la table 'employees'
        $sql = "SELECT r.*, rt.name AS type_name,
                CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                u.username AS reviewer_name
                FROM requests r
                LEFT JOIN request_types rt ON r.request_type_id = rt.id
                LEFT JOIN employees e ON r.user_id = e.id
                LEFT JOIN users u ON r.reviewed_by = u.id
                WHERE r.user_id = ?
                ORDER BY r.submitted_date DESC";

        return $this->connection->fetchAllAssociative($sql, [$employeeId]);
    }

   public function getByRhId(int $rhId): array
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

       return $this->connection->fetchAllAssociative($sql, [$rhId]);
   }

    public function getById(int $id): ?array
    {
        try {
            return $this->connection->fetchAssociative(
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
            ) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // UPDATE
    // ═══════════════════════════════════════════════════════════════

    public function update(int $id, array $data): bool
    {
        try {
            $data = $this->normalizeRequestInput($data);
            $this->connection->update('requests', [
                'title'       => $data['title'],
                'description' => $data['description'],
                'priority'    => $data['priority'],
                'updated_at'  => date('Y-m-d H:i:s'),
            ], ['id' => $id]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function updateStatus(int $id, string $status, int $reviewerId, ?string $comment = null): bool
    {
        try {
            $this->connection->update('requests', [
                'status'         => $status,
                'reviewed_by'    => $reviewerId,
                'reviewed_date'  => date('Y-m-d H:i:s'),
                'review_comment' => $comment,
                'updated_at'     => date('Y-m-d H:i:s'),
            ], ['id' => $id]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // DELETE
    // ═══════════════════════════════════════════════════════════════

    public function delete(int $id): bool
    {
        try {
            $this->connection->delete('requests', ['id' => $id]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════

    public function getStatusLabel(string $status): string
    {
        return match($status) {
            'pending'   => 'En attente',
            'approved'  => 'Approuvée',
            'rejected'  => 'Rejetée',
            'cancelled' => 'Annulée',
            default     => $status,
        };
    }

    public function getPriorityLabel(string $priority): string
    {
        return match($priority) {
            'low'    => 'Faible',
            'medium' => 'Moyenne',
            'high'   => 'Haute',
            default  => $priority,
        };
    }

    public function validateCreate(array $data): array
    {
        $data = $this->normalizeRequestInput($data);
        $errors = [];

        if ($data['user_id'] <= 0) {
            $errors['user_id'] = 'Utilisateur invalide.';
        }
        if ($data['request_type_id'] <= 0) {
            $errors['request_type_id'] = 'Type de demande obligatoire.';
        }
        if ($data['title'] === '' || mb_strlen($data['title']) < 3) {
            $errors['title'] = 'Le titre doit contenir au moins 3 caractères.';
        } elseif (mb_strlen($data['title']) > self::MAX_TITLE_LENGTH) {
            $errors['title'] = 'Le titre ne doit pas dépasser ' . self::MAX_TITLE_LENGTH . ' caractères.';
        }
        if ($data['priority'] === '' || !in_array($data['priority'], self::ALLOWED_PRIORITIES, true)) {
            $errors['priority'] = 'Priorité invalide.';
        }
        if ($data['description'] !== null && mb_strlen($data['description']) > self::MAX_DESCRIPTION_LENGTH) {
            $errors['description'] = 'La description est trop longue.';
        }

        return $errors;
    }

    public function validateUpdate(array $data): array
    {
        $data = $this->normalizeRequestInput($data);
        $errors = [];

        if ($data['title'] === '' || mb_strlen($data['title']) < 3) {
            $errors['title'] = 'Le titre doit contenir au moins 3 caractères.';
        } elseif (mb_strlen($data['title']) > self::MAX_TITLE_LENGTH) {
            $errors['title'] = 'Le titre ne doit pas dépasser ' . self::MAX_TITLE_LENGTH . ' caractères.';
        }
        if ($data['priority'] === '' || !in_array($data['priority'], self::ALLOWED_PRIORITIES, true)) {
            $errors['priority'] = 'Priorité invalide.';
        }
        if ($data['description'] !== null && mb_strlen($data['description']) > self::MAX_DESCRIPTION_LENGTH) {
            $errors['description'] = 'La description est trop longue.';
        }

        return $errors;
    }

    private function normalizeRequestInput(array $data): array
    {
        $title = trim((string)($data['title'] ?? ''));
        $description = $data['description'] ?? null;
        if (is_string($description)) {
            $description = trim($description);
            if ($description === '') {
                $description = null;
            }
        } else {
            $description = null;
        }

        $priority = strtolower(trim((string)($data['priority'] ?? '')));
        if ($priority === '') {
            $priority = 'medium';
        }

        return [
            'user_id' => (int)($data['user_id'] ?? 0),
            'request_type_id' => (int)($data['request_type_id'] ?? 0),
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'attachment_url' => $data['attachment_url'] ?? null,
        ];
    }
}
