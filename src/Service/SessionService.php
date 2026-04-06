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
        return $this->connection->fetchAllAssociative(
            'SELECT s.*, f.titre as formation_titre
             FROM session_formation s
             JOIN formation f ON s.id_formation = f.id_formation
             WHERE s.statut = "Ouverte" AND s.date_debut > NOW()'
        );
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