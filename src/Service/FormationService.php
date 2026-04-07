<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class FormationService
{
    public function __construct(private readonly Connection $connection) {}

    public function getAllFormations(): array
    {
        try {
            return $this->connection->fetchAllAssociative('SELECT * FROM formation ORDER BY created_at DESC');
        } catch (\Throwable) {
            return [];
        }
    }

    public function getFormationsByRhId(int $rhId, string $search = '', string $type = '', string $sort = 'created_at', string $dir = 'DESC'): array
    {
        try {
            $sql = 'SELECT * FROM formation WHERE id_rh = :rh_id';
            $params = ['rh_id' => $rhId];

            if (!empty($search)) {
                $sql .= ' AND (titre LIKE :search OR description LIKE :search)';
                $params['search'] = '%' . $search . '%';
            }

            if (!empty($type)) {
                $sql .= ' AND type = :type';
                $params['type'] = $type;
            }

            $allowedSorts = ['created_at', 'titre', 'duree'];
            $sort = in_array($sort, $allowedSorts) ? $sort : 'created_at';
            $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

            $sql .= " ORDER BY $sort $dir";

            return $this->connection->fetchAllAssociative($sql, $params);
        } catch (\Throwable) {
            return [];
        }
    }

    public function getFormationStatsByRhId(int $rhId): array
    {
        try {
            $total = $this->connection->fetchOne('SELECT COUNT(*) FROM formation WHERE id_rh = ?', [$rhId]);

            $activeSessions = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM session_formation sf
                 JOIN formation f ON sf.id_formation = f.id_formation
                 WHERE sf.statut = ? AND f.id_rh = ?',
                ['En cours', $rhId]
            );

            $totalParticipants = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM participation_formation pf
                 JOIN session_formation sf ON pf.id_session = sf.id_session
                 JOIN formation f ON sf.id_formation = f.id_formation
                 WHERE f.id_rh = ?',
                [$rhId]
            );

            return [
                'total_formations' => (int) $total,
                'active_sessions' => (int) $activeSessions,
                'total_participants' => (int) $totalParticipants,
            ];
        } catch (\Throwable) {
            return ['total_formations' => 0, 'active_sessions' => 0, 'total_participants' => 0];
        }
    }

    public function createFormation(array $data): void
    {
        $this->connection->insert('formation', [
            'titre' => $data['titre'],
            'description' => $data['description'],
            'type' => $data['type'],
            'duree' => $data['duree'],
            'organisme' => $data['organisme'],
            'objectifs' => $data['objectifs'],
            'id_rh' => $data['id_rh'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function updateFormation(int $id, array $data): void
    {
        $this->connection->update('formation', [
            'titre' => $data['titre'],
            'description' => $data['description'],
            'type' => $data['type'],
            'duree' => $data['duree'],
            'organisme' => $data['organisme'],
            'objectifs' => $data['objectifs'],
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id_formation' => $id]);
    }

    public function deleteFormation(int $id): void
    {
        $this->connection->delete('formation', ['id_formation' => $id]);
    }

    public function getFormationById(int $id): ?array
    {
        try {
            return $this->connection->fetchAssociative('SELECT * FROM formation WHERE id_formation = ?', [$id]);
        } catch (\Throwable) {
            return null;
        }
    }

    public function getSessionsByFormation(int $formationId): array
    {
        try {
            return $this->connection->fetchAllAssociative('SELECT * FROM session_formation WHERE id_formation = ? ORDER BY date_debut DESC', [$formationId]);
        } catch (\Throwable) {
            return [];
        }
    }

    public function createSession(array $data): void
    {
        $statut = $this->calculateStatut($data['date_debut'], $data['date_fin']);

        $this->connection->insert('session_formation', [
            'id_formation' => $data['id_formation'],
            'date_debut' => $data['date_debut'],
            'date_fin' => $data['date_fin'],
            'lieu' => $data['lieu'],
            'mode' => $data['mode'],
            'capacite_max' => $data['capacite_max'],
            'statut' => $statut,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function updateSession(int $id, array $data): void
    {
        $statut = $this->calculateStatut($data['date_debut'], $data['date_fin']);

        $this->connection->update('session_formation', [
            'date_debut' => $data['date_debut'],
            'date_fin' => $data['date_fin'],
            'lieu' => $data['lieu'],
            'mode' => $data['mode'],
            'capacite_max' => $data['capacite_max'],
            'statut' => $statut,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id_session' => $id]);
    }

    private function calculateStatut(string $dateDebut, string $dateFin): string
    {
        $now = new \DateTime();
        $now->setTime(0, 0, 0);

        $debut = new \DateTime($dateDebut);
        $debut->setTime(0, 0, 0);

        $fin = new \DateTime($dateFin);
        $fin->setTime(0, 0, 0);

        if ($now < $debut) {
            return 'Planifiee';
        } elseif ($now > $fin) {
            return 'Terminee';
        } else {
            return 'En cours';
        }
    }

    public function getFormationStats(): array
    {
        try {
            $total = $this->connection->fetchOne('SELECT COUNT(*) FROM formation');
            $activeSessions = $this->connection->fetchOne('SELECT COUNT(*) FROM session_formation WHERE statut = ?', ['En cours']);

            $totalParticipants = $this->connection->fetchOne('SELECT COUNT(*) FROM participation_formation');
            return [
                'total_formations' => (int) $total,
                'active_sessions' => (int) $activeSessions,
                'total_participants' => (int) $totalParticipants,
            ];
        } catch (\Throwable) {
            return ['total_formations' => 0, 'active_sessions' => 0, 'total_participants' => 0];
        }
    }
}