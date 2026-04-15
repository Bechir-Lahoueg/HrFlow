<?php

namespace App\Service;

use App\Entity\Rh\LeaveRequest;
use App\Repository\Rh\UserRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

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
    // Internal: send email
    // ──────────────────────────────────────────────
    private function send(string $to, string $subject, string $htmlBody): void
    {
        try {
            $email = (new Email())
                ->from($this->senderAddress)
                ->to($to)
                ->subject($subject)
                ->html($htmlBody);

            $this->mailer->send($email);
        } catch (\Throwable) {
            // Silently fail — email should never block the main workflow
        }
    }
}
