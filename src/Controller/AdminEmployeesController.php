<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminEmployeesController extends AbstractController
{
    #[Route('/welcome/admin/employees', name: 'app_admin_employees', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request, Connection $connection): Response
    {
        if ($request->isMethod('POST')) {
            $redirect = $this->handleCreateRh($request, $connection);

            if ($redirect !== null) {
                return $redirect;
            }
        }

        $employees = $connection->fetchAllAssociative(
            'SELECT first_name, last_name, job_title, email, rh_id FROM employees ORDER BY id DESC'
        );

        $users = $connection->fetchAllAssociative(
            'SELECT id, username, email, role, created_at FROM users ORDER BY id DESC'
        );

        return $this->render('DashboardAdmin/employees.html.twig', [
            'user' => $this->getUser(),
            'employees' => $employees,
            'users' => $users,
            'form' => [
                'first_name' => '',
                'last_name' => '',
                'age' => '',
                'job_title' => '',
                'email' => '',
            ],
        ]);
    }

    private function handleCreateRh(Request $request, Connection $connection): ?RedirectResponse
    {
        if (!$this->isCsrfTokenValid('create_rh', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide. Reessayez.');
            return $this->redirectToRoute('app_admin_employees');
        }

        $username = trim((string) $request->request->get('username', ''));
        $email = strtolower(trim((string) $request->request->get('email', '')));
        $password = (string) $request->request->get('password', '');

        if (
            $username === ''
            || $email === ''
            || $password === ''
        ) {
            $this->addFlash('error', 'Tous les champs sont obligatoires.');
            return $this->redirectToRoute('app_admin_employees');
        }

        $existingByEmail = $connection->fetchOne('SELECT COUNT(*) FROM users WHERE email = :email', [
            'email' => $email,
        ]);

        $existingByUsername = $connection->fetchOne('SELECT COUNT(*) FROM users WHERE username = :username', [
            'username' => $username,
        ]);

        if ((int) $existingByEmail > 0) {
            $this->addFlash('error', sprintf('Email deja utilise: %s', $email));
            return $this->redirectToRoute('app_admin_employees');
        }

        if ((int) $existingByUsername > 0) {
            $this->addFlash('error', sprintf('Username deja utilise: %s', $username));
            return $this->redirectToRoute('app_admin_employees');
        }

        // Keep compatibility with the Java module strategy: SHA-256 lower-case hex.
        $hashedPassword = hash('sha256', $password);

        $connection->insert('users', [
            'username' => $username,
            'email' => $email,
            'password' => $hashedPassword,
            'role' => 'RH',
        ]);

        $this->addFlash('success', 'RH cree avec succes.');
        return $this->redirectToRoute('app_admin_employees');
    }
}
