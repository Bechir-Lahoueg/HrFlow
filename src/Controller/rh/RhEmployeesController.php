<?php

namespace App\Controller\rh;

use App\Entity\Rh\Employee;
use App\Repository\Rh\EmployeeRepository;
use App\Repository\Rh\UserRepository;
use App\Security\DbUser;
use App\Service\LeaveRequestService;
use Doctrine\ORM\EntityManagerInterface;
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
    public function index(
        Request $request,
        EmployeeRepository $employeeRepository,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        LeaveRequestService $leaveRequestService,
    ): Response {
        if ($request->isMethod('POST')) {
            $redirect = $this->handleCreateEmployee($request, $employeeRepository, $userRepository, $em);
            if ($redirect !== null) {
                return $redirect;
            }
        }

        $rhId = $this->getCurrentRhId();

        $pendingLeaveCount = 0;
        try {
            $pendingLeaveCount = $leaveRequestService->getRhPendingCount($rhId);
        } catch (\Throwable) {
        }

        return $this->render('DashboardHr/employees.html.twig', [
            'user' => $this->getUser(),
            'employees' => $employeeRepository->findBy(['rhId' => $rhId], ['id' => 'DESC']),
            'pendingLeaveCount' => $pendingLeaveCount,
        ]);
    }

    private function handleCreateEmployee(
        Request $request,
        EmployeeRepository $employeeRepository,
        UserRepository $userRepository,
        EntityManagerInterface $em,
    ): ?RedirectResponse {
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

        if ($firstName === '' || $lastName === '' || $jobTitle === '' || $email === '' || $password === '' || $age <= 0) {
            $this->addFlash('error', 'Tous les champs employe sont obligatoires et valides.');
            return $this->redirectToRoute('app_rh_employees');
        }

        if ($employeeRepository->findOneBy(['email' => $email]) || $userRepository->existsByEmail($email)) {
            $this->addFlash('error', sprintf('Email deja utilise: %s', $email));
            return $this->redirectToRoute('app_rh_employees');
        }

        $employee = new Employee();
        $employee->setFirstName($firstName)
                 ->setLastName($lastName)
                 ->setAge($age)
                 ->setJobTitle($jobTitle)
                 ->setEmail($email)
                 ->setPassword(hash('sha256', $password))
                 ->setRhId($this->getCurrentRhId());

        $em->persist($employee);
        $em->flush();

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
