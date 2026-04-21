<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class FeedbackService
{
    private const ALLOWED_TYPES = ['performance', 'behavior', 'collaboration', 'other'];
    private const MAX_COMMENT_LENGTH = 2000;

    public function __construct(private readonly Connection $connection) {}

    // ═══════════════════════════════════════════════════════════════
    // CREATE
    // ═══════════════════════════════════════════════════════════════

    public function add(array $data): bool
    {
        try {
            $data = $this->normalizeFeedbackInput($data);
            $this->connection->insert('feedbacks', [
                'from_user_id'  => $data['from_user_id'],
                'to_user_id'    => $data['to_user_id'],
                'feedback_type' => $data['feedback_type'],
                'rating'        => $data['rating'],
                'comment'       => $data['comment'],
                'is_anonymous'  => $data['is_anonymous'] ? 1 : 0,
                'emotion_label' => $data['emotion_label'],
                'emotion_score' => $data['emotion_score'],
                'status'        => 'submitted',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // READ
    // ═══════════════════════════════════════════════════════════════

    public function getAll(): array
    {
        try {
            return $this->connection->fetchAllAssociative(
                "SELECT f.*,
                    CONCAT(e1.first_name, ' ', e1.last_name) AS from_username,
                    CONCAT(e2.first_name, ' ', e2.last_name) AS to_username
                FROM feedbacks f
                INNER JOIN employees e1 ON f.from_user_id = e1.id
                INNER JOIN employees e2 ON f.to_user_id   = e2.id
                ORDER BY f.created_at DESC"
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function getByRhId(int $rhId): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
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
            return $this->applyAnonymity($this->applyEmotionFallback($rows));
        } catch (\Throwable) {
            return [];
        }
    }

    public function getReceivedByEmployee(int $employeeId): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
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
            return $this->applyAnonymity($this->applyEmotionFallback($rows));
        } catch (\Throwable) {
            return [];
        }
    }

    public function getSentByEmployee(int $employeeId): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
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
            return $this->applyEmotionFallback($rows);
        } catch (\Throwable) {
            return [];
        }
    }

    public function getById(int $id): ?array
    {
        try {
            $row = $this->connection->fetchAssociative(
                "SELECT f.*,
                    CONCAT(e1.first_name, ' ', e1.last_name) AS from_username,
                    CONCAT(e2.first_name, ' ', e2.last_name) AS to_username
                FROM feedbacks f
                LEFT JOIN employees e1 ON f.from_user_id = e1.id
                LEFT JOIN employees e2 ON f.to_user_id   = e2.id
                WHERE f.id = ?",
                [$id]
            ) ?: null;

            if (!is_array($row)) {
                return null;
            }

            $normalized = $this->applyEmotionFallback([$row]);
            return $normalized[0] ?? $row;
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
            $data = $this->normalizeFeedbackInput($data);
            $this->connection->update('feedbacks', [
                'feedback_type' => $data['feedback_type'],
                'rating'        => $data['rating'],
                'comment'       => $data['comment'],
                'is_anonymous'  => $data['is_anonymous'] ? 1 : 0,
                'emotion_label' => $data['emotion_label'],
                'emotion_score' => $data['emotion_score'],
                'updated_at'    => date('Y-m-d H:i:s'),
            ], ['id' => $id]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function acknowledge(int $id): bool
    {
        try {
            $this->connection->update('feedbacks', ['status' => 'acknowledged'], ['id' => $id]);
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
            $this->connection->delete('feedbacks', ['id' => $id]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════

    public function validateCreate(array $data): array
    {
        $data = $this->normalizeFeedbackInput($data);
        $errors = [];

        if ($data['from_user_id'] <= 0) {
            $errors['from_user_id'] = 'Utilisateur émetteur invalide.';
        }
        if ($data['to_user_id'] <= 0) {
            $errors['to_user_id'] = 'Destinataire obligatoire.';
        }
        if ($data['from_user_id'] > 0 && $data['to_user_id'] > 0 && $data['from_user_id'] === $data['to_user_id']) {
            $errors['to_user_id'] = 'Vous ne pouvez pas vous auto-évaluer.';
        }
        if ($data['feedback_type'] === '' || !in_array($data['feedback_type'], self::ALLOWED_TYPES, true)) {
            $errors['feedback_type'] = 'Type de feedback invalide.';
        }
        if ($data['rating'] === null || $data['rating'] < 1 || $data['rating'] > 5) {
            $errors['rating'] = 'La note doit être comprise entre 1 et 5.';
        }
        if ($data['comment'] === null || mb_strlen($data['comment']) < 3) {
            $errors['comment'] = 'Le commentaire doit contenir au moins 3 caractères.';
        } elseif (mb_strlen($data['comment']) > self::MAX_COMMENT_LENGTH) {
            $errors['comment'] = 'Le commentaire est trop long.';
        }

        return $errors;
    }

    public function validateUpdate(array $data): array
    {
        $data = $this->normalizeFeedbackInput($data);
        $errors = [];

        if ($data['feedback_type'] === '' || !in_array($data['feedback_type'], self::ALLOWED_TYPES, true)) {
            $errors['feedback_type'] = 'Type de feedback invalide.';
        }
        if ($data['rating'] === null || $data['rating'] < 1 || $data['rating'] > 5) {
            $errors['rating'] = 'La note doit être comprise entre 1 et 5.';
        }
        if ($data['comment'] === null || mb_strlen($data['comment']) < 3) {
            $errors['comment'] = 'Le commentaire doit contenir au moins 3 caractères.';
        } elseif (mb_strlen($data['comment']) > self::MAX_COMMENT_LENGTH) {
            $errors['comment'] = 'Le commentaire est trop long.';
        }

        return $errors;
    }

    private function normalizeFeedbackInput(array $data): array
    {
        $feedbackType = strtolower(trim((string)($data['feedback_type'] ?? '')));
        $comment = $data['comment'] ?? null;
        if (is_string($comment)) {
            $comment = trim($comment);
            if ($comment === '') {
                $comment = null;
            }
        } else {
            $comment = null;
        }

        $rating = $data['rating'] ?? null;
        if (is_array($rating)) {
            $rating = end($rating);
        }
        $rating = is_numeric($rating) ? (int)$rating : null;

        return [
            'from_user_id' => (int)($data['from_user_id'] ?? 0),
            'to_user_id' => (int)($data['to_user_id'] ?? 0),
            'feedback_type' => $feedbackType,
            'rating' => $rating,
            'comment' => $comment,
            'is_anonymous' => !empty($data['is_anonymous']),
            'emotion_label' => strtolower(trim((string)($data['emotion_label'] ?? 'unknown'))),
            'emotion_score' => max(0.0, min(1.0, (float)($data['emotion_score'] ?? 0.0))),
        ];
    }

    private function applyAnonymity(array $rows): array
    {
        return array_map(function ($row) {
            if ($row['is_anonymous']) {
                $row['from_username'] = '👤 Anonyme';
            }
            return $row;
        }, $rows);
    }

    private function applyEmotionFallback(array $rows): array
    {
        return array_map(function ($row) {
            $label = strtolower(trim((string) ($row['emotion_label'] ?? 'unknown')));
            $score = (float) ($row['emotion_score'] ?? 0.0);

            if ($label !== '' && $label !== 'unknown' && $score > 0.0) {
                return $row;
            }

            $rating = (int) ($row['rating'] ?? 0);
            if ($rating >= 4) {
                $row['emotion_label'] = 'joy';
                $row['emotion_score'] = 0.6;
            } elseif ($rating <= 2 && $rating > 0) {
                $row['emotion_label'] = 'anger';
                $row['emotion_score'] = 0.6;
            } else {
                $row['emotion_label'] = 'neutral';
                $row['emotion_score'] = 0.5;
            }

            return $row;
        }, $rows);
    }

    public function getColleagues(int $employeeId): array
    {
        try {
            return $this->connection->fetchAllAssociative(
                "SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) AS fullname
                FROM employees e
                WHERE e.rh_id = (SELECT rh_id FROM employees WHERE id = ?)
                AND e.id != ?
                ORDER BY e.first_name",
                [$employeeId, $employeeId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function getRatingStars(int $rating): string
    {
        return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
    }
}
