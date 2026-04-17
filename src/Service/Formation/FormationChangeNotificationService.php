<?php

namespace App\Service\Formation;

use App\Entity\Formation\EmployeeNotification;
use App\Entity\Formation\Formation;
use App\Entity\Formation\SessionFormation;
use App\Entity\Rh\LeaveNotification;
use App\Repository\Formation\ParticipationFormationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class FormationChangeNotificationService
{
    public function __construct(
        private readonly ParticipationFormationRepository $participationRepository,
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notifyFormationUpdated(Formation $formation): int
    {
        $subject = sprintf('Mise a jour de la formation: %s', (string) $formation->getTitre());
        $message = sprintf(
            "Bonjour,\n\nLa formation '%s' a ete modifiee par le service RH.\nMerci de consulter votre espace employe pour voir les changements.\n\nEquipe HrFlow",
            (string) $formation->getTitre()
        );

        return $this->sendToEmployeesForFormation($formation, 'formation_updated', $subject, $message);
    }

    public function notifyFormationDeleted(Formation $formation): int
    {
        $subject = sprintf('Annulation de la formation: %s', (string) $formation->getTitre());
        $message = sprintf(
            "Bonjour,\n\nLa formation '%s' a ete supprimee/annulee par le service RH.\nMerci de consulter votre espace employe pour plus d'informations.\n\nEquipe HrFlow",
            (string) $formation->getTitre()
        );

        return $this->sendToEmployeesForFormation($formation, 'formation_deleted', $subject, $message);
    }

    public function notifySessionUpdated(SessionFormation $session): int
    {
        $formation = $session->getFormation();
        if ($formation === null) {
            return 0;
        }

        $subject = sprintf('Mise a jour de session: %s', (string) $formation->getTitre());
        $message = sprintf(
            "Bonjour,\n\nLa session de la formation '%s' a ete modifiee (dates/lieu/mode/capacite).\nMerci de consulter votre espace employe pour voir les changements.\n\nEquipe HrFlow",
            (string) $formation->getTitre()
        );

        $employees = $this->participationRepository->findNotifiableEmployeesBySession((int) $session->getId());
        if ($employees === []) {
            return 0;
        }

        return $this->notifyEmployees($employees, $subject, $message, 'session_updated', $formation, $session);
    }

    public function notifySessionDeleted(SessionFormation $session): int
    {
        $formation = $session->getFormation();
        if ($formation === null) {
            return 0;
        }

        $subject = sprintf('Suppression de session: %s', (string) $formation->getTitre());
        $message = sprintf(
            "Bonjour,\n\nLa session de la formation '%s' a ete supprimee par le service RH.\nMerci de consulter votre espace employe pour les prochaines disponibilites.\n\nEquipe HrFlow",
            (string) $formation->getTitre()
        );

        $employees = $this->participationRepository->findNotifiableEmployeesBySession((int) $session->getId());
        if ($employees === []) {
            return 0;
        }

        return $this->notifyEmployees($employees, $subject, $message, 'session_deleted', $formation, $session);
    }

    private function sendToEmployeesForFormation(Formation $formation, string $type, string $subject, string $message): int
    {
        $employees = $this->participationRepository->findNotifiableEmployeesByFormation((int) $formation->getId());
        if ($employees === []) {
            return 0;
        }

        return $this->notifyEmployees($employees, $subject, $message, $type, $formation, null);
    }

    private function notifyEmployees(array $employees, string $subject, string $message, string $type, Formation $formation, ?SessionFormation $session): int
    {
        $queuedInApp = 0;
        $sentByMail = 0;

        foreach ($employees as $employee) {
            $referenceType = $session ? 'session' : 'formation';
            $referenceId = $session ? (int) $session->getId() : (int) $formation->getId();

            $notification = (new EmployeeNotification())
                ->setEmployee($employee)
                ->setReferenceType($referenceType)
                ->setReferenceId($referenceId)
                ->setTitle((string) $formation->getTitre())
                ->setType($type)
                ->setMessage($message)
                ->setIsRead(false);

            $this->em->persist($notification);

            $leaveStyleNotification = (new LeaveNotification())
                ->setRecipientType(LeaveNotification::RECIPIENT_EMPLOYEE)
                ->setRecipientId((int) $employee->getId())
                ->setLeaveRequestId($referenceId)
                ->setType($type)
                ->setTitle((string) $formation->getTitre())
                ->setMessage($message)
                ->setIsRead(false);

            $this->em->persist($leaveStyleNotification);
            $queuedInApp++;

            $emailAddress = $employee->getEmail();
            if (!$emailAddress) {
                continue;
            }

            try {
                $email = (new Email())
                    ->from('ytkachi911@gmail.com')
                    ->to($emailAddress)
                    ->subject($subject)
                    ->text($message);

                $this->mailer->send($email);
                $sentByMail++;
            } catch (\Throwable $e) {
                $this->logger->error('Echec envoi notification formation.', [
                    'formation_id' => $formation->getId(),
                    'employee_id' => $employee->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->em->flush();

        return max($sentByMail, $queuedInApp);
    }
}



