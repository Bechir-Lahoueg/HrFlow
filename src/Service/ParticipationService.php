<?php

namespace App\Service;

use App\Entity\Formation\ParticipationFormation;
use App\Repository\Rh\EmployeeRepository;
use App\Repository\Rh\LeaveRequestRepository;
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
        private readonly LeaveRequestRepository $leaveRequestRepository,
        private readonly PresenceService $presenceService,
    ) {
    }

    /**
     * @return array{ok: bool, reasons: string[]}
     */
    public function registerEmployeeDetailed(int $employeeId, int $sessionId): array
    {
        try {
            $reasons = [];

            if ($this->participationRepository->findByEmployeeAndSession($employeeId, $sessionId)) {
                $reasons[] = 'Vous etes deja inscrit a une session de cette formation';
            }

            $employee = $this->employeeRepository->find($employeeId);
            $session = $this->sessionFormationRepository->find($sessionId);
            if (!$employee || !$session) {
                return ['ok' => false, 'reasons' => ['Session introuvable.']];
            }

            $formation = $session->getFormation();
            if (!$formation) {
                return ['ok' => false, 'reasons' => ['Formation introuvable.']];
            }

            if ($this->participationRepository->hasAcceptedInFormation($employeeId, (int) $formation->getId())) {
                $reasons[] = 'Vous etes deja inscrit a une autre session de cette formation';
            }

            $start = $session->getDateDebut();
            $end = $session->getDateFin();
            if ($start && $end && $this->leaveRequestRepository->hasAcceptedDateOverlap($employeeId, $start, $end)) {
                $reasons[] = 'Cette session chevauche avec une periode de conge validee';
            }

            if ($reasons !== []) {
                return ['ok' => false, 'reasons' => array_values(array_unique($reasons))];
            }

            $participation = new ParticipationFormation();
            $participation->setEmployee($employee)
                ->setSession($session)
                ->setDateInscription(new \DateTime())
                ->setStatutParticipation('Inscrit')
                ->setCertificatObtenu(false);

            $this->em->persist($participation);
            $this->em->flush();

            return ['ok' => true, 'reasons' => []];
        } catch (\Throwable) {
            return ['ok' => false, 'reasons' => ['Erreur technique, veuillez reessayer.']];
        }
    }

    public function registerEmployee(int $employeeId, int $sessionId): bool
    {
        return $this->registerEmployeeDetailed($employeeId, $sessionId)['ok'];
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

    /** @return ParticipationFormation[] */
    public function getRhParticipations(int $rhId, string $status = ''): array
    {
        return $this->participationRepository->findByRhId($rhId, $status);
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
