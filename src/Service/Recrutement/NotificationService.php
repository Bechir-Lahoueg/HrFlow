<?php

namespace App\Service\Recrutement;

use App\Entity\Recrutement\Application;
use App\Entity\Recrutement\Interview;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class NotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly string $senderAddress
    ) {
    }

    /**
     * Send status update notification to candidate
     */
    public function sendStatusUpdateEmail(Application $application): void
    {
        if (!$application->getEmailAddress()) {
            return;
        }

        $statusLabels = [
            'PENDING' => 'En attente',
            'REVIEWING' => 'En revue',
            'INTERVIEW' => 'Entretien',
            'OFFER' => 'Offre',
            'HIRED' => 'Recruté',
            'REJECTED' => 'Rejeté',
        ];

        $statusLabel = $statusLabels[$application->getStatus()] ?? $application->getStatus();

        $email = (new Email())
            ->from($this->senderAddress)
            ->to($application->getEmailAddress())
            ->subject('Mise à jour de votre candidature - ' . $application->getJobOffer()->getTitle())
            ->html($this->twig->render('emails/application_status_update.html.twig', [
                'application' => $application,
                'statusLabel' => $statusLabel,
            ]));

        $this->mailer->send($email);
    }

    /**
     * Send interview scheduled notification to candidate
     */
    public function sendInterviewScheduledEmail(Interview $interview): void
    {
        $application = $interview->getApplication();
        if (!$application || !$application->getEmailAddress()) {
            return;
        }

        $email = (new Email())
            ->from($this->senderAddress)
            ->to($application->getEmailAddress())
            ->subject('Entretien planifié - ' . $application->getJobOffer()->getTitle())
            ->html($this->twig->render('emails/interview_scheduled.html.twig', [
                'interview' => $interview,
                'application' => $application,
            ]));

        $this->mailer->send($email);
    }

    /**
     * Send interview result notification to candidate
     */
    public function sendInterviewResultEmail(Interview $interview): void
    {
        $application = $interview->getApplication();
        if (!$application || !$application->getEmailAddress()) {
            return;
        }

        $resultLabels = [
            'PENDING' => 'En attente',
            'PASSED' => 'Réussi',
            'FAILED' => 'Échoué',
            'NO_SHOW' => 'Absent',
        ];

        $resultLabel = $resultLabels[$interview->getResult()] ?? $interview->getResult();

        $email = (new Email())
            ->from($this->senderAddress)
            ->to($application->getEmailAddress())
            ->subject('Résultat de votre entretien - ' . $application->getJobOffer()->getTitle())
            ->html($this->twig->render('emails/interview_result.html.twig', [
                'interview' => $interview,
                'application' => $application,
                'resultLabel' => $resultLabel,
            ]));

        $this->mailer->send($email);
    }
}