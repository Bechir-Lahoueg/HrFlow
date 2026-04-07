<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class PresenceService
{
    public function __construct(private readonly Connection $connection) {}

    public function getPresencesBySessionAndDate(int $sessionId, string $date): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT p.* FROM presence_formation p
             JOIN participation_formation pf ON p.id_participation = pf.id_participation
             WHERE pf.id_session = :sessionId AND p.date_presence = :date',
            ['sessionId' => $sessionId, 'date' => $date]
        );
    }

    public function getPresencesBySession(int $sessionId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT p.* FROM presence_formation p
             JOIN participation_formation pf ON p.id_participation = pf.id_participation
             WHERE pf.id_session = :sessionId',
            ['sessionId' => $sessionId]
        );
    }

    public function savePresences(string $date, array $presencesData): void
    {
        $this->connection->beginTransaction();
        try {
            foreach ($presencesData as $participationId => $statut) {
                // Check if a record already exists
                $existing = $this->connection->fetchOne(
                    'SELECT id_presence FROM presence_formation WHERE id_participation = :pid AND date_presence = :d',
                    ['pid' => $participationId, 'd' => $date]
                );

                if ($existing) {
                    $this->connection->update('presence_formation', [
                        'statut' => $statut
                    ], ['id_presence' => $existing]);
                } else {
                    $this->connection->insert('presence_formation', [
                        'id_participation' => $participationId,
                        'date_presence' => $date,
                        'statut' => $statut
                    ]);
                }
            }
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function getAttendancePercentage(int $participationId): float
    {
        $sessionData = $this->connection->fetchAssociative(
            'SELECT s.date_debut, s.date_fin, f.duree
             FROM participation_formation pf
             JOIN session_formation s ON pf.id_session = s.id_session
             JOIN formation f ON s.id_formation = f.id_formation
             WHERE pf.id_participation = :pid',
            ['pid' => $participationId]
        );

        if (!$sessionData) {
            return 0.0;
        }

        $recordedDays = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM presence_formation WHERE id_participation = :pid",
            ['pid' => $participationId]
        );

        if ($recordedDays === 0) {
            return 0.0;
        }

        $presentDays = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM presence_formation
             WHERE id_participation = :pid AND statut IN ('Present', 'Justifie')",
            ['pid' => $participationId]
        );

        return round(($presentDays / $recordedDays) * 100, 2);
    }
}
