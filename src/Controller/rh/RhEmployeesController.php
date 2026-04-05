<?php

namespace App\Controller\rh;

use App\Security\DbUser;
use App\Service\LeaveRequestService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class RhEmployeesController extends AbstractController
{
    #[Route('/welcome/rh/employees', name: 'app_rh_employees', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RH')]
    public function index(Request $request, Connection $connection, LeaveRequestService $leaveRequestService): Response
    {
        if ($request->isMethod('POST')) {
            $redirect = $this->handleCreateEmployee($request, $connection);
            if ($redirect !== null) {
                return $redirect;
            }
        }

        $rhId = $this->getCurrentRhId();
        $employees = $connection->fetchAllAssociative(
            'SELECT first_name, last_name, age, job_title, email, rh_id FROM employees WHERE rh_id = :rhId ORDER BY id DESC',
            ['rhId' => $rhId]
        );

        $pendingLeaveCount = 0;
        try {
            $pendingLeaveCount = $leaveRequestService->getRhPendingCount($rhId);
        } catch (\Throwable) {
            // Leave module might be unavailable during early setup.
        }

        return $this->render('DashboardHr/employees.html.twig', [
            'user' => $this->getUser(),
            'employees' => $employees,
            'pendingLeaveCount' => $pendingLeaveCount,
        ]);
    }

    private function handleCreateEmployee(Request $request, Connection $connection): ?RedirectResponse
    {
        if (!$this->isCsrfTokenValid('create_employee_rh', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide. Reessayez.');
            return $this->redirectToRoute('app_rh_employees');
        }

        $firstName = trim((string) $request->request->get('first_name', ''));
        $lastName = trim((string) $request->request->get('last_name', ''));
        $age = (int) $request->request->get('age', 0);
        $jobTitle = trim((string) $request->request->get('job_title', ''));
        $email = strtolower(trim((string) $request->request->get('email', '')));
        $password = (string) $request->request->get('password', '');

        if (
            $firstName === ''
            || $lastName === ''
            || $jobTitle === ''
            || $email === ''
            || $password === ''
            || $age <= 0
        ) {
            $this->addFlash('error', 'Tous les champs employe sont obligatoires et valides.');
            return $this->redirectToRoute('app_rh_employees');
        }

        $existingEmployeeByEmail = $connection->fetchOne('SELECT COUNT(*) FROM employees WHERE email = :email', [
            'email' => $email,
        ]);

        $existingUserByEmail = $connection->fetchOne('SELECT COUNT(*) FROM users WHERE email = :email', [
            'email' => $email,
        ]);

        if ((int) $existingEmployeeByEmail > 0 || (int) $existingUserByEmail > 0) {
            $this->addFlash('error', sprintf('Email deja utilise: %s', $email));
            return $this->redirectToRoute('app_rh_employees');
        }

        // Java-compatible strategy: SHA-256 lower-case hex.
        $hashedPassword = hash('sha256', $password);

        $connection->insert('employees', [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'age' => $age,
            'job_title' => $jobTitle,
            'email' => $email,
            'password' => $hashedPassword,
            'rh_id' => $this->getCurrentRhId(),
        ]);

        $this->addFlash('success', 'Employe cree avec succes.');
        return $this->redirectToRoute('app_rh_employees');
    }

    private function getCurrentRhId(): int
    {
        $user = $this->getUser();

        if (!$user instanceof DbUser) {
            throw $this->createAccessDeniedException('Utilisateur RH invalide.');
        }

        return $user->getId();
    }
}
