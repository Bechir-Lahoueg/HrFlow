<?php

namespace App\Service;

use App\Entity\PresenceFormation;
use App\Repository\ParticipationFormationRepository;
use App\Repository\PresenceFormationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PresenceService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PresenceFormationRepository $presenceRepository,
        private readonly ParticipationFormationRepository $participationRepository,
    ) {
    }

    /** @return PresenceFormation[] */
    public function getPresencesBySession(int $sessionId): array
    {
        return $this->presenceRepository->findBySession($sessionId);
    }

    public function savePresences(string $date, array $presencesData): void
    {
        $this->em->beginTransaction();
        try {
            foreach ($presencesData as $participationId => $statut) {
                $existing = $this->presenceRepository->findOneByParticipationAndDate((int) $participationId, $date);

                if ($existing) {
                    $existing->setStatut($statut);
                } else {
                    $participation = $this->participationRepository->find((int) $participationId);
                    if (!$participation) {
                        continue;
                    }

                    $presence = new PresenceFormation();
                    $presence->setParticipation($participation)
                        ->setDatePresence(new \DateTime($date))
                        ->setStatut($statut);

                    $this->em->persist($presence);
                }
            }

            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function getAttendancePercentage(int $participationId): float
    {
        $recordedDays = $this->presenceRepository->countByParticipation($participationId);
        if ($recordedDays === 0) {
            return 0.0;
        }

        $presentDays = $this->presenceRepository->countPresentByParticipation($participationId);

        return round(($presentDays / $recordedDays) * 100, 2);
    }
}
