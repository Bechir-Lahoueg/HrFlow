<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class SessionService
{
    public function __construct(private readonly Connection $connection) {}

    public function getSessionsByFormation(int $formationId): array
    {
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
        $now = new \DateTime();
        $this->connection->executeStatement(
            'UPDATE session_formation SET statut = "Cloturee" WHERE date_fin < :now',
            ['now' => $now->format('Y-m-d H:i:s')]
        );
    }

    public function getIdFormationBySessionId(int $sessionId): int
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