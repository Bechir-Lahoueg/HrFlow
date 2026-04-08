<?php

namespace App\Service;

use App\Entity\Formation\SessionFormation;
use App\Repository\Formation\SessionFormationRepository;

final class SessionService
{
    public function __construct(private readonly SessionFormationRepository $sessionFormationRepository)
    {
    }

    /** @return SessionFormation[] */
    public function getSessionsByFormation(int $formationId): array
    {
        return $this->sessionFormationRepository->findByFormation($formationId);
    }

    /** @return SessionFormation[] */
    public function getAvailableSessions(): array
    {
        return $this->sessionFormationRepository->findAvailable();
    }

    public function getIdFormationBySessionId(int $sessionId): int
    {
        $session = $this->sessionFormationRepository->find($sessionId);

        return $session?->getFormation()?->getId() ?? 0;
    }

    public function getSessionById(int $sessionId): ?SessionFormation
    {
        return $this->sessionFormationRepository->find($sessionId);
    }
}
