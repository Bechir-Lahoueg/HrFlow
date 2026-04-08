<?php

namespace App\Service;

use App\Entity\Formation\ParticipationFormation;
use App\Repository\Rh\EmployeeRepository;
use App\Repository\Formation\ParticipationFormationRepository;
use App\Repository\Formation\SessionFormationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ParticipationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ParticipationFormationRepository $participationRepository,
        private readonly SessionFormationRepository $sessionFormationRepository,
        private readonly EmployeeRepository $employeeRepository,
        private readonly PresenceService $presenceService,
    ) {
    }

    public function registerEmployee(int $employeeId, int $sessionId): bool
    {
        try {
            if ($this->participationRepository->findByEmployeeAndSession($employeeId, $sessionId)) {
                return false;
            }

            $employee = $this->employeeRepository->find($employeeId);
            $session = $this->sessionFormationRepository->find($sessionId);
            if (!$employee || !$session) {
                return false;
            }

            $participation = new ParticipationFormation();
            $participation->setEmployee($employee)
                ->setSession($session)
                ->setDateInscription(new \DateTime())
                ->setStatutParticipation('Inscrit')
                ->setCertificatObtenu(false);

            $this->em->persist($participation);
            $this->em->flush();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return ParticipationFormation[] */
    public function getEmployeeParticipations(int $employeeId): array
    {
        return $this->participationRepository->findByEmployee($employeeId);
    }

    /** @return ParticipationFormation[] */
    public function getSessionParticipations(int $sessionId): array
    {
        return $this->participationRepository->findBySession($sessionId);
    }

    public function updateStatus(int $participationId, string $status): void
    {
        $participation = $this->participationRepository->find($participationId);
        if (!$participation) {
            return;
        }

        $participation->setStatutParticipation($status);
        $this->em->flush();
    }

    public function getAttendancePercentage(int $participationId): float
    {
        return $this->presenceService->getAttendancePercentage($participationId);
    }
}
