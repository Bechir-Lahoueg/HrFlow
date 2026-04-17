<?php

namespace App\Service\Formation;

use App\Entity\Formation\SessionFeedback;
use App\Repository\Formation\ParticipationFormationRepository;
use App\Repository\Formation\SessionFeedbackRepository;
use Doctrine\ORM\EntityManagerInterface;

final class SessionFeedbackService
{
    public function __construct(
        private readonly ParticipationFormationRepository $participationRepository,
        private readonly SessionFeedbackRepository $feedbackRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function submitFeedback(
        int $employeeId,
        int $sessionId,
        int $rating,
        string $comment,
        bool $isAnonymous
    ): array {
        $participation = $this->participationRepository->findByEmployeeAndSession($employeeId, $sessionId);
        if (!$participation) {
            return ['ok' => false, 'message' => 'Feedback impossible : vous n etes pas inscrit a cette session.'];
        }

        if ($participation->getStatutParticipation() !== 'Accepte' && $participation->getStatutParticipation() !== 'Certificat obtenu') {
            return ['ok' => false, 'message' => 'Feedback autorise uniquement pour les participations acceptees.'];
        }

        $session = $participation->getSession();
        if (!$session || $session->getStatut() !== 'Terminee') {
            return ['ok' => false, 'message' => 'Vous pouvez donner un feedback uniquement apres la fin de la session.'];
        }

        if ($this->feedbackRepository->hasFeedbackForSessionAndUser($sessionId, $employeeId)) {
            return ['ok' => false, 'message' => 'Vous avez deja donne un feedback pour cette session.'];
        }

        $rating = max(1, min(5, $rating));
        $comment = trim($comment);
        if ($comment === '') {
            return ['ok' => false, 'message' => 'Le commentaire est obligatoire.'];
        }

        $feedback = (new SessionFeedback())
            ->setEmployee($participation->getEmployee())
            ->setFormation($session->getFormation())
            ->setSession($session)
            ->setRating($rating)
            ->setComment($comment)
            ->setIsAnonymous($isAnonymous)
            ->setRecommande($rating >= 4);

        $this->em->persist($feedback);
        $this->em->flush();

        return ['ok' => true, 'message' => 'Merci, votre feedback a ete enregistre.'];
    }

    public function getAverageRatingsByFormationIds(array $formationIds): array
    {
        return $this->feedbackRepository->getAverageMapByFormationIds($formationIds);
    }

    public function getFeedbacksByFormation(int $formationId): array
    {
        return $this->feedbackRepository->findByFormation($formationId);
    }
}


