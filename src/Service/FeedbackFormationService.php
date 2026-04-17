<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class FeedbackFormationService
{
    private const MAX_COMMENT_LENGTH = 1000;

    public function __construct(private readonly Connection $connection) {}

    // ═══════════════════════════════════════════════════════════════
    // CREATE
    // ═══════════════════════════════════════════════════════════════

    public function add(array $data): bool
    {
        try {
            $data = $this->normalizeFeedbackFormationInput($data);
            $this->connection->insert('feedback_formation', [
                'user_id'                => $data['user_id'],
                'formation_id'           => $data['formation_id'],
                'session_id'             => $data['session_id'],
                'rating'                 => $data['rating'],
                'contenu_comment'        => $data['contenu_comment'],
                'formateur_comment'      => $data['formateur_comment'],
                'organisation_comment'   => $data['organisation_comment'],
                'recommande'             => $data['recommande'] ? 1 : 0,
                'created_at'             => date('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // READ
    // ═══════════════════════════════════════════════════════════════

    public function getByUser(int $userId): array
    {
        try {
            return $this->connection->fetchAllAssociative(
                "SELECT ff.*,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    f.titre AS formation_name,
                    CONCAT(sf.date_debut, ' — ', sf.lieu) AS session_name
                FROM feedback_formation ff
                LEFT JOIN employees        e  ON ff.user_id      = e.id
                LEFT JOIN formation         f  ON ff.formation_id = f.id_formation
                LEFT JOIN session_formation sf ON ff.session_id   = sf.id_session
                WHERE ff.user_id = ?
                ORDER BY ff.created_at DESC",
                [$userId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function getByRhId(int $rhId): array
    {
        try {
            return $this->connection->fetchAllAssociative(
                "SELECT ff.*,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    f.titre AS formation_name,
                    CONCAT(sf.date_debut, ' — ', sf.lieu) AS session_name
                FROM feedback_formation ff
                INNER JOIN employees        e  ON ff.user_id      = e.id
                LEFT JOIN  formation         f  ON ff.formation_id = f.id_formation
                LEFT JOIN  session_formation sf ON ff.session_id   = sf.id_session
                WHERE e.rh_id = ?
                ORDER BY ff.created_at DESC",
                [$rhId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function getById(int $id): ?array
    {
        try {
            return $this->connection->fetchAssociative(
                "SELECT ff.*,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    f.titre AS formation_name,
                    CONCAT(sf.date_debut, ' — ', sf.lieu) AS session_name
                FROM feedback_formation ff
                LEFT JOIN employees        e  ON ff.user_id      = e.id
                LEFT JOIN formation         f  ON ff.formation_id = f.id_formation
                LEFT JOIN session_formation sf ON ff.session_id   = sf.id_session
                WHERE ff.id = ?",
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
            $data = $this->normalizeFeedbackFormationInput($data);
            $this->connection->update('feedback_formation', [
                'rating'               => $data['rating'],
                'contenu_comment'      => $data['contenu_comment'],
                'formateur_comment'    => $data['formateur_comment'],
                'organisation_comment' => $data['organisation_comment'],
                'recommande'           => $data['recommande'] ? 1 : 0,
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
            $this->connection->delete('feedback_formation', ['id' => $id]);
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
        $data = $this->normalizeFeedbackFormationInput($data);
        $errors = [];

        if ($data['user_id'] <= 0) {
            $errors['user_id'] = 'Utilisateur invalide.';
        }
        if ($data['formation_id'] <= 0) {
            $errors['formation_id'] = 'Formation obligatoire.';
        }
        if ($data['session_id'] !== null && $data['session_id'] <= 0) {
            $errors['session_id'] = 'Session invalide.';
        }
        if ($data['rating'] === null || $data['rating'] < 1 || $data['rating'] > 5) {
            $errors['rating'] = 'La note doit être comprise entre 1 et 5.';
        }
        if ($data['contenu_comment'] !== null && mb_strlen($data['contenu_comment']) > self::MAX_COMMENT_LENGTH) {
            $errors['contenu_comment'] = 'Commentaire contenu trop long.';
        }
        if ($data['formateur_comment'] !== null && mb_strlen($data['formateur_comment']) > self::MAX_COMMENT_LENGTH) {
            $errors['formateur_comment'] = 'Commentaire formateur trop long.';
        }
        if ($data['organisation_comment'] !== null && mb_strlen($data['organisation_comment']) > self::MAX_COMMENT_LENGTH) {
            $errors['organisation_comment'] = 'Commentaire organisation trop long.';
        }

        return $errors;
    }

    public function validateUpdate(array $data): array
    {
        $data = $this->normalizeFeedbackFormationInput($data);
        $errors = [];

        if ($data['rating'] === null || $data['rating'] < 1 || $data['rating'] > 5) {
            $errors['rating'] = 'La note doit être comprise entre 1 et 5.';
        }
        if ($data['contenu_comment'] !== null && mb_strlen($data['contenu_comment']) > self::MAX_COMMENT_LENGTH) {
            $errors['contenu_comment'] = 'Commentaire contenu trop long.';
        }
        if ($data['formateur_comment'] !== null && mb_strlen($data['formateur_comment']) > self::MAX_COMMENT_LENGTH) {
            $errors['formateur_comment'] = 'Commentaire formateur trop long.';
        }
        if ($data['organisation_comment'] !== null && mb_strlen($data['organisation_comment']) > self::MAX_COMMENT_LENGTH) {
            $errors['organisation_comment'] = 'Commentaire organisation trop long.';
        }

        return $errors;
    }

    private function normalizeFeedbackFormationInput(array $data): array
    {
        $rating = $data['rating'] ?? null;
        if (is_array($rating)) {
            $rating = end($rating);
        }
        $rating = is_numeric($rating) ? (int)$rating : null;

        $sessionId = $data['session_id'] ?? null;
        $sessionId = $sessionId === '' ? null : $sessionId;
        $sessionId = is_numeric($sessionId) ? (int)$sessionId : null;

        $contenuComment = $this->normalizeOptionalText($data['contenu_comment'] ?? null);
        $formateurComment = $this->normalizeOptionalText($data['formateur_comment'] ?? null);
        $organisationComment = $this->normalizeOptionalText($data['organisation_comment'] ?? null);

        return [
            'user_id' => (int)($data['user_id'] ?? 0),
            'formation_id' => (int)($data['formation_id'] ?? 0),
            'session_id' => $sessionId,
            'rating' => $rating,
            'contenu_comment' => $contenuComment,
            'formateur_comment' => $formateurComment,
            'organisation_comment' => $organisationComment,
            'recommande' => !empty($data['recommande']),
        ];
    }

    private function normalizeOptionalText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        return $value === '' ? null : $value;
    }

    public function getApprovedFormations(int $employeeId): array
    {
        try {
            return $this->connection->fetchAllAssociative(
                "SELECT DISTINCT f.id_formation, f.titre
                FROM formation f
                JOIN session_formation s ON f.id_formation = s.id_formation
                JOIN participation_formation p ON s.id_session = p.id_session
                WHERE p.id_utilisateur = ? AND p.statut_participation = 'Accepte'
                ORDER BY f.titre",
                [$employeeId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function getApprovedSessionsForFormation(int $formationId, int $employeeId): array
    {
        try {
            return $this->connection->fetchAllAssociative(
                "SELECT s.id_session, s.date_debut, s.lieu
                FROM session_formation s
                JOIN participation_formation p ON s.id_session = p.id_session
                WHERE s.id_formation = ? AND p.id_utilisateur = ? AND p.statut_participation = 'Accepte'",
                [$formationId, $employeeId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function getAverageRating(int $formationId): float
    {
        try {
            return (float) $this->connection->fetchOne(
                'SELECT AVG(rating) FROM feedback_formation WHERE formation_id = ?',
                [$formationId]
            );
        } catch (\Throwable) {
            return 0.0;
        }
    }

    public function getRatingStars(int $rating): string
    {
        return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
    }
}
