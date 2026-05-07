<?php

namespace App\Controller\admin;

use App\Entity\Rh\User;
use App\Repository\Rh\EmployeeRepository;
use App\Repository\Rh\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    public function index(
        Request $request,
        EmployeeRepository $employeeRepository,
        UserRepository $userRepository,
        EntityManagerInterface $em,
    ): Response {
        if ($request->isMethod('POST')) {
            return $this->handleCreateRh($request, $userRepository, $em);
        }

        $users = $userRepository->findBy([], ['id' => 'DESC']);
        $rhById = [];
        foreach ($users as $userItem) {
            if ($userItem->getRole() !== 'RH') {
                continue;
            }

            $rhById[(int) $userItem->getId()] = [
                'username' => $userItem->getUsername(),
                'email' => $userItem->getEmail(),
                'department' => $userItem->getDepartment(),
            ];
        }

        return $this->render('DashboardAdmin/employees.html.twig', [
            'user' => $this->getUser(),
            'employees' => $employeeRepository->findBy([], ['id' => 'DESC']),
            'users' => $users,
            'rhById' => $rhById,
            'departments' => [
                'Marketing',
                'Developpement',
                'Ressources Humaines',
                'Finance',
                'Commercial',
                'Support',
                'Design',
                'Produit',
            ],
            'form' => [
                'first_name' => '',
                'last_name' => '',
                'age' => '',
                'job_title' => '',
                'email' => '',
                'department' => '',
            ],
        ]);
    }

    #[Route('/welcome/admin/employees/rh/{id}/edit', name: 'app_admin_rh_edit', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function editRh(
        int $id,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('admin_edit_rh_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_employees');
        }

        $user = $userRepository->find($id);
        if ($user === null || $user->getRole() !== 'RH') {
            $this->addFlash('error', 'Compte RH introuvable.');
            return $this->redirectToRoute('app_admin_employees');
        }

        $username = trim((string) $request->request->get('username', ''));
        $email = strtolower(trim((string) $request->request->get('email', '')));
        $password = (string) $request->request->get('password', '');
        $department = trim((string) $request->request->get('department', ''));

        if ($username === '' || $email === '' || $department === '') {
            $this->addFlash('error', 'Le nom d\'utilisateur, l\'email et le departement sont obligatoires.');
            return $this->redirectToRoute('app_admin_employees');
        }

        $existingByEmail = $userRepository->findOneBy(['email' => $email]);
        if ($existingByEmail !== null && $existingByEmail->getId() !== $user->getId()) {
            $this->addFlash('error', sprintf('Email deja utilise: %s', $email));
            return $this->redirectToRoute('app_admin_employees');
        }

        $existingByUsername = $userRepository->findOneBy(['username' => $username]);
        if ($existingByUsername !== null && $existingByUsername->getId() !== $user->getId()) {
            $this->addFlash('error', sprintf('Username deja utilise: %s', $username));
            return $this->redirectToRoute('app_admin_employees');
        }

        $user->setUsername($username)->setEmail($email)->setDepartment($department);

        if ($password !== '') {
            $user->setPassword(hash('sha256', $password));
        }

        $em->flush();

        $this->addFlash('success', sprintf('RH "%s" mis a jour avec succes.', $username));
        return $this->redirectToRoute('app_admin_employees');
    }

    private function handleCreateRh(Request $request, UserRepository $userRepository, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('create_rh', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide. Reessayez.');
            return $this->redirectToRoute('app_admin_employees');
        }

        $username = trim((string) $request->request->get('username', ''));
        $email = strtolower(trim((string) $request->request->get('email', '')));
        $password = (string) $request->request->get('password', '');
        $department = trim((string) $request->request->get('department', ''));

        if ($username === '' || $email === '' || $password === '' || $department === '') {
            $this->addFlash('error', 'Tous les champs sont obligatoires (departement inclus).');
            return $this->redirectToRoute('app_admin_employees');
        }

        if ($userRepository->existsByEmail($email)) {
            $this->addFlash('error', sprintf('Email deja utilise: %s', $email));
            return $this->redirectToRoute('app_admin_employees');
        }

        if ($userRepository->existsByUsername($username)) {
            $this->addFlash('error', sprintf('Username deja utilise: %s', $username));
            return $this->redirectToRoute('app_admin_employees');
        }

        $user = new User();
        $user->setUsername($username)
             ->setEmail($email)
             ->setPassword(hash('sha256', $password))
               ->setDepartment($department)
             ->setRole('RH');

        $em->persist($user);
        $em->flush();

        $this->addFlash('success', 'RH cree avec succes.');
        return $this->redirectToRoute('app_admin_employees');
    }
}
