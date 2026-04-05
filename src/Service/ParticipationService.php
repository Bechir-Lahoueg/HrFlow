<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class ParticipationService
{
    public function __construct(private readonly Connection $connection) {}

    public function registerEmployee(int $userId, int $sessionId): bool
    {
        try {
            // Vérifier si déjà inscrit
            $existing = $this->connection->fetchOne(
                'SELECT id_participation FROM participation_formation WHERE id_utilisateur = :u AND id_session = :s',
                ['u' => $userId, 's' => $sessionId]
            );

            if ($existing) return false;

            $this->connection->insert('participation_formation', [
                'id_utilisateur' => $userId,
                'id_session' => $sessionId,
                'date_inscription' => date('Y-m-d'),
                'statut_participation' => 'Inscrit',
                'certificat_obtenu' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getEmployeeParticipations(int $userId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT p.*, f.titre, s.date_debut
             FROM participation_formation p
             JOIN session_formation s ON p.id_session = s.id_session
             JOIN formation f ON s.id_formation = f.id_formation
             WHERE p.id_utilisateur = :userId',
            ['userId' => $userId]
        );
    }
}