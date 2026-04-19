<?php

namespace App\Service\Shared;

use App\Entity\Formation\ParticipationFormation;
use App\Entity\Rh\LeaveRequest;
use App\Repository\Rh\UserRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Symfony\Component\Mime\Address;


/**
 * Central mailing service for HrFlow.
 *
 * 4 email types:
 *  1. Login alert          → RH / Admin only
 *  2. Leave decision       → Employee (accepted / refused)
 *  3. New leave request    → RH notification
 *  4. Exception pending    → Admin notification
 */
final class HrFlowMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly UserRepository $userRepository,
        private readonly string $senderAddress,
    ) {
    }

    // ──────────────────────────────────────────────
    // 1. Login alert (RH & Admin only)
    // ──────────────────────────────────────────────
    public function sendLoginAlert(string $recipientEmail, string $recipientName, string $role, string $ip, string $userAgent): void
    {
        $html = $this->twig->render('emails/login_alert.html.twig', [
            'name' => $recipientName,
            'role' => $role,
            'ip' => $ip,
            'userAgent' => $userAgent,
            'date' => new \DateTime('now'),
        ]);

        $this->send($recipientEmail, 'Alerte connexion — HrFlow', $html);
    }

    // ──────────────────────────────────────────────
    // 2. Leave decision notification (Employee)
    // ──────────────────────────────────────────────
    public function sendLeaveDecision(LeaveRequest $leave, string $decision): void
    {
        $employee = $leave->getEmployee();
        if (!$employee || !$employee->getEmail()) {
            return;
        }

        $html = $this->twig->render('emails/leave_decision.html.twig', [
            'employee' => $employee,
            'leave' => $leave,
            'decision' => $decision, // 'ACCEPTE' or 'REFUSE'
        ]);

        $subject = $decision === 'ACCEPTE'
            ? 'Votre demande de conge a ete acceptee — HrFlow'
            : 'Votre demande de conge a ete refusee — HrFlow';

        $this->send($employee->getEmail(), $subject, $html);
    }

    // ──────────────────────────────────────────────
    // 3. New leave request notification (→ RH)
    // ──────────────────────────────────────────────
    public function sendNewRequestNotification(LeaveRequest $leave): void
    {
        $employee = $leave->getEmployee();
        if (!$employee) {
            return;
        }

        // Find the RH user via the employee's rhId
        $rhUser = $this->userRepository->find($employee->getRhId());
        if (!$rhUser || !$rhUser->getEmail()) {
            return;
        }

        $html = $this->twig->render('emails/new_leave_request.html.twig', [
            'employee' => $employee,
            'leave' => $leave,
        ]);

        $this->send(
            $rhUser->getEmail(),
            sprintf('Nouvelle demande de conge de %s — HrFlow', $employee->getFullName()),
            $html
        );
    }

    // ──────────────────────────────────────────────
    // 4. Exception pending Admin (→ Admin)
    // ──────────────────────────────────────────────
    public function sendExceptionPendingAdmin(LeaveRequest $leave): void
    {
        $employee = $leave->getEmployee();
        if (!$employee) {
            return;
        }

        // Find all Admin users
        $admins = $this->userRepository->findBy(['role' => 'ADMIN']);

        if (empty($admins)) {
            return;
        }

        $html = $this->twig->render('emails/exception_pending_admin.html.twig', [
            'employee' => $employee,
            'leave' => $leave,
        ]);

        foreach ($admins as $admin) {
            if ($admin->getEmail()) {
                $this->send(
                    $admin->getEmail(),
                    sprintf('Demande exceptionnelle en attente — %s — HrFlow', $employee->getFullName()),
                    $html
                );
            }
        }
    }

    // ──────────────────────────────────────────────
    // 5. Account settings changed (→ User)
    // ──────────────────────────────────────────────
    /**
     * @param string[] $changes  e.g. ['email', 'mot de passe']
     */
    public function sendAccountChangedNotification(string $recipientEmail, string $username, array $changes, ?string $newEmail = null): void
    {
        $html = $this->twig->render('emails/account_changed.html.twig', [
            'username' => $username,
            'changes' => $changes,
            'newEmail' => $newEmail,
            'date' => new \DateTime('now'),
        ]);

        $this->send($recipientEmail, 'Modification de votre compte — HrFlow', $html);

        // If the email changed, also notify the new address
        if ($newEmail && $newEmail !== $recipientEmail) {
            $this->send($newEmail, 'Modification de votre compte — HrFlow', $html);
        }
    }

    // ──────────────────────────────────────────────
    // 6. Formation participation accepted (→ Employee)
    // ──────────────────────────────────────────────
    public function sendFormationAccepted(ParticipationFormation $participation): void
    {
        $employee = $participation->getEmployee();
        $session = $participation->getSession();
        $formation = $session?->getFormation();

        if (!$employee || !$employee->getEmail() || !$session || !$formation) {
            return;
        }

        $lieu = trim((string) ($session->getLieu() ?? ''));
        $isOnlineLink = str_starts_with(strtolower($lieu), 'http://') || str_starts_with(strtolower($lieu), 'https://');

        $html = $this->twig->render('emails/formation_accepted.html.twig', [
            'employee' => $employee,
            'formation' => $formation,
            'session' => $session,
            'isOnlineLink' => $isOnlineLink,
            'date' => new \DateTime('now'),
        ]);

        $this->send(
            $employee->getEmail(),
            sprintf('Participation acceptee: %s — HrFlow', (string) $formation->getTitre()),
            $html
        );
    }

    // ──────────────────────────────────────────────
    // 7. Certificate available (→ Employee)
    // ──────────────────────────────────────────────
    public function sendCertificateAvailable(ParticipationFormation $participation): void
    {
        $employee = $participation->getEmployee();
        $session = $participation->getSession();
        $formation = $session?->getFormation();

        if (!$employee || !$employee->getEmail() || !$formation) {
            return;
        }

        $html = $this->twig->render('emails/certificate_available.html.twig', [
            'employee' => $employee,
            'formation' => $formation,
            'date' => new \DateTime('now'),
        ]);

        $this->send(
            $employee->getEmail(),
            sprintf('Votre certificat est disponible: %s — HrFlow', (string) $formation->getTitre()),
            $html
        );
    }

    // ──────────────────────────────────────────────
    // Internal: send email
    // ──────────────────────────────────────────────
    private function send(string $to, string $subject, string $htmlBody): void
    {
        try {
            $email = (new Email())
                ->from(new Address($this->senderAddress, 'HR-Flow Team'))
                ->to($to)
                ->subject($subject)
                ->html($htmlBody);

            $this->mailer->send($email);
        } catch (\Throwable) {
            // Silently fail — email should never block the main workflow
        }
    }
}
