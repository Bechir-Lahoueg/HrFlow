<?php

namespace App\Service\Recrutement;

use App\Entity\Recrutement\Application;
use App\Entity\Recrutement\Interview;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class NotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly string $senderAddress,
        private readonly bool $emailsEnabled,
        private readonly ?LoggerInterface $logger
    ) {
    }

    /**
     * Send status update notification to candidate
     */
    public function sendStatusUpdateEmail(Application $application): void
    {
        $emailAddress = $application->getEmailAddress();
        if (!$emailAddress) {
            $this->log('warning', 'No email address for application ID {id}', ['id' => $application->getId()]);
            return;
        }

        $jobOffer = $application->getJobOffer();
        if (!$jobOffer) {
            $this->log('error', 'No job offer for application ID {id}', ['id' => $application->getId()]);
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
            ->to($emailAddress)
            ->subject('Mise à jour de votre candidature - ' . $jobOffer->getTitle())
            ->html($this->twig->render('emails/application_status_update.html.twig', [
                'application' => $application,
                'statusLabel' => $statusLabel,
            ]));

        if (!$this->emailsEnabled) {
            $this->log('info', 'Recruitment email skipped (disabled): Status update for application {id}', ['id' => $application->getId()]);
            return;
        }
        $this->mailer->send($email);
        $this->log('info', 'Status update email sent to {email} for application {id}', ['email' => $emailAddress, 'id' => $application->getId()]);
    }

    /**
     * Send interview scheduled notification to candidate
     */
    public function sendInterviewScheduledEmail(Interview $interview): void
    {
        $application = $interview->getApplication();
        if (!$application) {
            $this->log('error', 'No application for interview ID {id}', ['id' => $interview->getId()]);
            return;
        }

        $emailAddress = $application->getEmailAddress();
        if (!$emailAddress) {
            $this->log('warning', 'No email address for application ID {id}', ['id' => $application->getId()]);
            return;
        }

        $jobOffer = $application->getJobOffer();
        if (!$jobOffer) {
            $this->log('error', 'No job offer for application ID {id}', ['id' => $application->getId()]);
            return;
        }

        $email = (new Email())
            ->from($this->senderAddress)
            ->to($emailAddress)
            ->subject('Entretien planifié - ' . $jobOffer->getTitle())
            ->html($this->twig->render('emails/interview_scheduled.html.twig', [
                'interview' => $interview,
                'application' => $application,
            ]));

        if (!$this->emailsEnabled) {
            $this->log('info', 'Recruitment email skipped (disabled): Interview scheduled for interview {id}', ['id' => $interview->getId()]);
            return;
        }
        $this->mailer->send($email);
        $this->log('info', 'Interview scheduled email sent to {email} for interview {id}', ['email' => $emailAddress, 'id' => $interview->getId()]);
    }

    /**
     * Send interview result notification to candidate
     */
    public function sendInterviewResultEmail(Interview $interview): void
    {
        $application = $interview->getApplication();
        if (!$application) {
            $this->log('error', 'No application for interview ID {id}', ['id' => $interview->getId()]);
            return;
        }

        $emailAddress = $application->getEmailAddress();
        if (!$emailAddress) {
            $this->log('warning', 'No email address for application ID {id}', ['id' => $application->getId()]);
            return;
        }

        $jobOffer = $application->getJobOffer();
        if (!$jobOffer) {
            $this->log('error', 'No job offer for application ID {id}', ['id' => $application->getId()]);
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
            ->to($emailAddress)
            ->subject('Résultat de votre entretien - ' . $jobOffer->getTitle())
            ->html($this->twig->render('emails/interview_result.html.twig', [
                'interview' => $interview,
                'application' => $application,
                'resultLabel' => $resultLabel,
            ]));

        if (!$this->emailsEnabled) {
            $this->log('info', 'Recruitment email skipped (disabled): Interview result for interview {id}', ['id' => $interview->getId()]);
            return;
        }
        $this->mailer->send($email);
        $this->log('info', 'Interview result email sent to {email} for interview {id}', ['email' => $emailAddress, 'id' => $interview->getId()]);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->$level($message, $context);
        }
    }
}