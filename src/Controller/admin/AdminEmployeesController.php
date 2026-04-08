<?php

namespace App\Controller\admin;

use App\Entity\User;
use App\Repository\EmployeeRepository;
use App\Repository\UserRepository;
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
            $redirect = $this->handleCreateRh($request, $userRepository, $em);
            if ($redirect !== null) {
                return $redirect;
            }
        }

        return $this->render('DashboardAdmin/employees.html.twig', [
            'user' => $this->getUser(),
            'employees' => $employeeRepository->findBy([], ['id' => 'DESC']),
            'users' => $userRepository->findBy([], ['id' => 'DESC']),
            'form' => [
                'first_name' => '',
                'last_name' => '',
                'age' => '',
                'job_title' => '',
                'email' => '',
            ],
        ]);
    }

    private function handleCreateRh(Request $request, UserRepository $userRepository, EntityManagerInterface $em): ?RedirectResponse
    {
        if (!$this->isCsrfTokenValid('create_rh', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide. Reessayez.');
            return $this->redirectToRoute('app_admin_employees');
        }

        $username = trim((string) $request->request->get('username', ''));
        $email = strtolower(trim((string) $request->request->get('email', '')));
        $password = (string) $request->request->get('password', '');

        if ($username === '' || $email === '' || $password === '') {
            $this->addFlash('error', 'Tous les champs sont obligatoires.');
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
             ->setRole('RH');

        $em->persist($user);
        $em->flush();

        $this->addFlash('success', 'RH cree avec succes.');
        return $this->redirectToRoute('app_admin_employees');
    }
}
