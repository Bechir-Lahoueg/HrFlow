<?php

namespace App\Controller;

use App\Form\Shared\AccountSettingsType;
use App\Security\DbUser;
use App\Service\Shared\HrFlowMailer;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AccountSettingsController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly HrFlowMailer $mailer,
    ) {
    }

    #[Route('/admin/settings', name: 'app_admin_settings', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminSettings(Request $request): Response
    {
        return $this->handleSettings($request, 'DashboardAdmin/settings.html.twig', [
            'sidebarTemplate' => 'DashboardAdmin/components/_sidebar.html.twig',
            'headerTemplate' => 'DashboardAdmin/components/_header.html.twig',
            'dashboardLabel' => 'Admin',
            'accentColor' => 'violet',
        ]);
    }

    #[Route('/rh/settings', name: 'app_rh_settings', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RH')]
    public function rhSettings(Request $request): Response
    {
        return $this->handleSettings($request, 'DashboardHr/settings.html.twig', [
            'sidebarTemplate' => 'DashboardHr/components/_sidebar.html.twig',
            'headerTemplate' => 'DashboardHr/components/_header.html.twig',
            'dashboardLabel' => 'RH',
            'accentColor' => 'teal',
        ]);
    }

    #[Route('/employee/settings', name: 'app_employee_settings', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function employeeSettings(Request $request): Response
    {
        return $this->handleSettings($request, 'DashboardEmployee/settings.html.twig', [
            'sidebarTemplate' => 'DashboardEmployee/components/_sidebar.html.twig',
            'headerTemplate' => 'DashboardEmployee/components/_header.html.twig',
            'dashboardLabel' => 'Employe',
            'accentColor' => 'amber',
        ]);
    }

    /**
     * @param array<string, string> $config
     */
    private function handleSettings(Request $request, string $template, array $config): Response
    {
        $user = $this->getUser();
        if (!$user instanceof DbUser) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(AccountSettingsType::class, [
            'email' => $user->getEmail(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = trim((string) $form->get('email')->getData());
            $currentPassword = (string) $form->get('currentPassword')->getData();
            $currentEmail = (string) $user->getEmail();

            $newPasswordFirst = $form->get('newPassword')->get('first')->getData();
            $newPassword = (string) ($newPasswordFirst ?? '');

            // Verify current password against SHA-256 stored hash
            if (!$this->isPasswordValid($currentPassword, $user->getPassword())) {
                $form->get('currentPassword')->addError(new FormError('Le mot de passe actuel est incorrect.'));
            }

            if ($newPassword !== '' && strlen($newPassword) < 8) {
                $form->get('newPassword')->addError(new FormError('Le nouveau mot de passe doit contenir au moins 8 caracteres.'));
            }

            if ($email !== $currentEmail && $this->isEmailAlreadyUsed($email, $user)) {
                $form->get('email')->addError(new FormError('Cet email est deja utilise par un autre compte.'));
            }

            if ($email === $currentEmail && $newPassword === '') {
                $form->addError(new FormError('Aucune modification detectee.'));
            }

            if ($form->getErrors(true)->count() > 0) {
                $this->addFlash('error', 'La modification a echoue. Veuillez verifier les erreurs ci-dessous.');
            } else {
                $data = ['email' => $email];
                $table = 'users';
                $emailChanged = $email !== $currentEmail;
                $passwordChanged = $newPassword !== '';

                if ($passwordChanged) {
                    // Store as SHA-256 to stay consistent with the rest of the system
                    $data['password'] = hash('sha256', $newPassword);
                }

                if ($user->getSource() === 'employees') {
                    $table = 'employees';
                    $data['updated_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
                }

                try {
                    $this->connection->update($table, $data, ['id' => $user->getId()]);
                } catch (\Throwable) {
                    $this->addFlash('error', 'Une erreur technique est survenue. Veuillez reessayer.');
                    return $this->redirect($request->getUri());
                }

                // Send confirmation email
                $notifEmail = $emailChanged ? $currentEmail : $email;
                if ($notifEmail) {
                    $changes = [];
                    if ($emailChanged) {
                        $changes[] = 'email';
                    }
                    if ($passwordChanged) {
                        $changes[] = 'mot de passe';
                    }
                    $this->mailer->sendAccountChangedNotification(
                        $notifEmail,
                        $user->getUserIdentifier(),
                        $changes,
                        $emailChanged ? $email : null,
                    );
                }

                if ($emailChanged && $passwordChanged) {
                    $this->addFlash('success', 'Votre email et mot de passe ont ete mis a jour avec succes.');
                } elseif ($passwordChanged) {
                    $this->addFlash('success', 'Votre mot de passe a ete mis a jour. Il sera actif a la prochaine connexion.');
                } else {
                    $this->addFlash('success', 'Votre email a ete mis a jour avec succes.');
                }

                return $this->redirect($request->getUri());
            }
        }

        return $this->render($template, [
            'form' => $form->createView(),
            'user' => $user,
            'sidebarTemplate' => $config['sidebarTemplate'],
            'headerTemplate' => $config['headerTemplate'],
            'dashboardLabel' => $config['dashboardLabel'],
            'accentColor' => $config['accentColor'],
        ]);
    }

    private function isPasswordValid(string $plainPassword, string $storedPassword): bool
    {
        // Primary: SHA-256 hex comparison (standard storage in this project)
        if (hash_equals($storedPassword, hash('sha256', $plainPassword))) {
            return true;
        }

        // Fallback: bcrypt/argon2 (in case some passwords were migrated)
        if (password_get_info($storedPassword)['algo'] !== null) {
            return password_verify($plainPassword, $storedPassword);
        }

        // Last resort: plain-text comparison
        return hash_equals($storedPassword, $plainPassword);
    }

    private function isEmailAlreadyUsed(string $email, DbUser $currentUser): bool
    {
        $excludeUsersId = $currentUser->getSource() === 'users' ? $currentUser->getId() : null;
        $excludeEmployeesId = $currentUser->getSource() === 'employees' ? $currentUser->getId() : null;

        return $this->emailExistsInTable('users', $email, $excludeUsersId)
            || $this->emailExistsInTable('employees', $email, $excludeEmployeesId)
            || $this->emailExistsInTable('candidates', $email, null);
    }

    private function emailExistsInTable(string $table, string $email, ?int $excludeId): bool
    {
        $sql = sprintf('SELECT id FROM %s WHERE email = :email', $table);
        $params = ['email' => $email];

        if ($excludeId !== null) {
            $sql .= ' AND id != :excludeId';
            $params['excludeId'] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        return $this->connection->fetchOne($sql, $params) !== false;
    }
}
