<?php

namespace App\Service;

use App\Repository\Formation\SessionFeedbackRepository;

final class FeedbackFormationService
{
    private const MAX_COMMENT_LENGTH = 1000;

    public function __construct(private readonly SessionFeedbackRepository $feedbackRepository) {}

    // ═══════════════════════════════════════════════════════════════
    // CREATE
    // ═══════════════════════════════════════════════════════════════

    public function add(array $data): bool
    {
        try {
            $data = $this->normalizeFeedbackFormationInput($data);
            $this->feedbackRepository->insertFeedback([
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
            return $this->feedbackRepository->fetchByUser($userId);
        } catch (\Throwable) {
            return [];
        }
    }

    public function getByRhId(int $rhId): array
    {
        try {
            return $this->feedbackRepository->fetchByRhId($rhId);
        } catch (\Throwable) {
            return [];
        }
    }

    public function getById(int $id): ?array
    {
        try {
            return $this->feedbackRepository->fetchById($id);
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
            $this->feedbackRepository->updateFeedback($id, [
                'rating'               => $data['rating'],
                'contenu_comment'      => $data['contenu_comment'],
                'formateur_comment'    => $data['formateur_comment'],
                'organisation_comment' => $data['organisation_comment'],
                'recommande'           => $data['recommande'] ? 1 : 0,
            ]);
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
            $this->feedbackRepository->deleteFeedback($id);
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
            return $this->feedbackRepository->fetchApprovedFormations($employeeId);
        } catch (\Throwable) {
            return [];
        }
    }

    public function getApprovedSessionsForFormation(int $formationId, int $employeeId): array
    {
        try {
            return $this->feedbackRepository->fetchApprovedSessionsForFormation($formationId, $employeeId);
        } catch (\Throwable) {
            return [];
        }
    }

    public function getAverageRating(int $formationId): float
    {
        try {
            return $this->feedbackRepository->getAverageRating($formationId);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    public function getRatingStars(int $rating): string
    {
        return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
    }
}
