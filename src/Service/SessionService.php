<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class SessionService
{
    public function __construct(private readonly Connection $connection) {}

    public function getSessionsByFormation(int $formationId): array
    {
        $this->autoUpdateSessionStatuses();
        return $this->connection->fetchAllAssociative(
            'SELECT * FROM session_formation WHERE id_formation = :id',
            ['id' => $formationId]
        );
    }

    public function getAvailableSessions(): array
    {
        $this->autoUpdateSessionStatuses();
        return $this->connection->fetchAllAssociative(
            'SELECT s.*, f.titre as formation_titre
             FROM session_formation s
             JOIN formation f ON s.id_formation = f.id_formation
             WHERE s.statut = "Planifiee" AND s.date_debut > NOW()'
        );
    }

    private function autoUpdateSessionStatuses(): void
    {
        try {
            $this->connection->executeStatement("
                UPDATE session_formation
                SET statut = CASE
                    WHEN DATE(date_debut) > CURRENT_DATE THEN 'Planifiee'
                    WHEN DATE(date_fin) < CURRENT_DATE THEN 'Terminee'
                    ELSE 'En cours'
                END
                WHERE statut NOT IN ('Annulee')
            ");
        } catch (\Throwable $e) {
            // Ignore if error
        }
    }

    public function getFormationIdBySessionId(int $sessionId): int
    {
        $result = $this->connection->fetchOne(
            'SELECT id_formation FROM session_formation WHERE id_session = :id',
            ['id' => $sessionId]
        );
        return $result ? (int)$result : 0;
    }

    public function getSessionById(int $sessionId): ?array
    {
        return $this->connection->fetchAssociative(
            'SELECT * FROM session_formation WHERE id_session = :id',
            ['id' => $sessionId]
        );
    }
}