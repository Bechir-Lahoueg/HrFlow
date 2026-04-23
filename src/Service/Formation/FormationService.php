<?php

namespace App\Service\Formation;

use App\Entity\Formation\Formation;
use App\Entity\Formation\SessionFormation;
use App\Repository\Formation\FormationRepository;
use App\Repository\Formation\ParticipationFormationRepository;
use App\Repository\Formation\PresenceFormationRepository;
use App\Repository\Formation\SessionFeedbackRepository;
use App\Repository\Formation\SessionFormationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class FormationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FormationRepository $formationRepository,
        private readonly SessionFormationRepository $sessionFormationRepository,
        private readonly ParticipationFormationRepository $participationRepository,
        private readonly PresenceFormationRepository $presenceRepository,
        private readonly SessionFeedbackRepository $sessionFeedbackRepository,
    ) {
    }

    /** @return Formation[] */
    public function getFormationsByRhId(int $rhId, string $search = '', string $type = '', string $sort = 'createdAt', string $dir = 'DESC'): array
    {
        // Map legacy sort names from templates
        $sortMap = ['created_at' => 'createdAt'];
        $sort = $sortMap[$sort] ?? $sort;

        return $this->formationRepository->findByRh($rhId, $search, $type, $sort, $dir);
    }

    /** @return Formation[] */
    public function getAllFormations(string $search = '', string $type = '', string $sort = 'createdAt', string $dir = 'DESC', string $organisme = ''): array
    {
        $sortMap = ['created_at' => 'createdAt'];
        $sort = $sortMap[$sort] ?? $sort;

        return $this->formationRepository->findAllFiltered($search, $type, $sort, $dir, $organisme);
    }

    public function getFormationStatsByRhId(int $rhId): array
    {
        try {
            return $this->formationRepository->getStatsByRh($rhId);
        } catch (\Throwable) {
            return ['total_formations' => 0, 'active_sessions' => 0, 'total_participants' => 0];
        }
    }

    /**
     * @return array{
     *   total_formations:int,
     *   total_participations:int,
     *   accepted_participations:int,
     *   participation_rate:float,
     *   formations_by_month:array<string,int>,
     *   formations_by_category:array<string,int>,
     *   participation_status_counts:array<string,int>
     * }
     */
    public function getRhDashboardMetrics(int $rhId, int $months = 6): array
    {
        try {
            return $this->formationRepository->getRhDashboardMetrics($rhId, $months);
        } catch (\Throwable) {
            $fallbackMonths = [];
            $startMonth = (new \DateTimeImmutable('first day of this month'))->modify('-' . (max(3, min(12, $months)) - 1) . ' months');
            for ($i = 0; $i < max(3, min(12, $months)); $i++) {
                $fallbackMonths[$startMonth->modify('+' . $i . ' months')->format('M Y')] = 0;
            }

            return [
                'total_formations' => 0,
                'total_participations' => 0,
                'accepted_participations' => 0,
                'participation_rate' => 0.0,
                'formations_by_month' => $fallbackMonths,
                'formations_by_category' => [],
                'participation_status_counts' => [
                    'accepted' => 0,
                    'refused' => 0,
                    'pending' => 0,
                ],
            ];
        }
    }

    /** @return array{topFormations: array<int, array<string, mixed>>, topFormateurs: array<int, array<string, mixed>>} */
    public function getTopInsightsByRhId(int $rhId): array
    {
        try {
            return [
                'topFormations' => $this->formationRepository->findTopFormationsByRh($rhId, 5),
                'topFormateurs' => $this->formationRepository->findTopFormateursByRh($rhId, 5),
            ];
        } catch (\Throwable) {
            return [
                'topFormations' => [],
                'topFormateurs' => [],
            ];
        }
    }

    public function createFormation(array $data): Formation
    {
        $formation = new Formation();
        $formation->setTitre($data['titre'])
            ->setDescription($data['description'])
            ->setType($data['type'])
            ->setDuree((int) $data['duree'])
            ->setOrganisme($data['organisme'])
            ->setObjectifs($data['objectifs'])
            ->setRhId((int) $data['id_rh']);

        $this->em->persist($formation);
        $this->em->flush();

        return $formation;
    }

    public function updateFormation(int $id, array $data): void
    {
        $formation = $this->formationRepository->find($id);
        if (!$formation) {
            return;
        }

        $formation->setTitre($data['titre'])
            ->setDescription($data['description'])
            ->setType($data['type'])
            ->setDuree((int) $data['duree'])
            ->setOrganisme($data['organisme'])
            ->setObjectifs($data['objectifs']);

        $this->em->flush();
    }

    public function deleteFormation(int $id): void
    {
        $formation = $this->formationRepository->find($id);
        if (!$formation) {
            return;
        }

        $this->em->remove($formation);
        $this->em->flush();
    }

    public function deleteSessionWithRelations(SessionFormation $session): void
    {
        $sessionId = (int) $session->getId();

        $this->em->beginTransaction();
        try {
            $this->sessionFeedbackRepository->deleteBySessionId($sessionId);
            $this->presenceRepository->deleteBySessionId($sessionId);
            $this->participationRepository->deleteBySessionId($sessionId);

            $managedSession = $this->sessionFormationRepository->find($sessionId);
            if ($managedSession !== null) {
                $this->em->remove($managedSession);
                $this->em->flush();
            }

            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function deleteFormationWithRelations(Formation $formation): void
    {
        $formationId = (int) $formation->getId();

        $this->em->beginTransaction();
        try {
            $this->sessionFeedbackRepository->deleteByFormationId($formationId);
            $this->presenceRepository->deleteByFormationId($formationId);
            $this->participationRepository->deleteByFormationId($formationId);
            $this->sessionFormationRepository->deleteByFormationId($formationId);

            $managedFormation = $this->formationRepository->find($formationId);
            if ($managedFormation !== null) {
                $this->em->remove($managedFormation);
                $this->em->flush();
            }

            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function getFormationById(int $id): ?Formation
    {
        return $this->formationRepository->find($id);
    }

    /** @return SessionFormation[] */
    public function getSessionsByFormation(int $formationId): array
    {
        return $this->sessionFormationRepository->findByFormation($formationId);
    }

    public function createSession(array $data): SessionFormation
    {
        $formation = $this->formationRepository->find((int) $data['id_formation']);
        if (!$formation) {
            throw new \RuntimeException('Formation introuvable.');
        }

        $statut = $this->calculateStatut($data['date_debut'], $data['date_fin']);

        $session = new SessionFormation();
        $session->setFormation($formation)
            ->setDateDebut(new \DateTime($data['date_debut']))
            ->setDateFin(new \DateTime($data['date_fin']))
            ->setLieu($data['lieu'])
            ->setMode($data['mode'])
            ->setCapaciteMax((int) $data['capacite_max'])
            ->setStatut($statut);

        $this->em->persist($session);
        $this->em->flush();

        return $session;
    }

    public function updateSession(int $id, array $data): void
    {
        $session = $this->sessionFormationRepository->find($id);
        if (!$session) {
            return;
        }

        $statut = $this->calculateStatut($data['date_debut'], $data['date_fin']);

        $session->setDateDebut(new \DateTime($data['date_debut']))
            ->setDateFin(new \DateTime($data['date_fin']))
            ->setLieu($data['lieu'])
            ->setMode($data['mode'])
            ->setCapaciteMax((int) $data['capacite_max'])
            ->setStatut($statut);

        $this->em->flush();
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
}
