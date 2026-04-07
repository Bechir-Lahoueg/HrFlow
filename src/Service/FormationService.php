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