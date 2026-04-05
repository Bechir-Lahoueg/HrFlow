<?php

namespace App\Controller\employer;

use App\Service\SessionService;
use App\Service\ParticipationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/employee/formation')]
final class EmployeeFormationController extends AbstractController
{
    public function __construct(
        private readonly SessionService $sessionService,
        private readonly ParticipationService $participationService
    ) {}

    #[Route('/available', name: 'employee_formation_available')]
    public function available(): Response
    {
        return $this->render('DashboardEmployee/formation_index.html.twig', [
            'sessions' => $this->sessionService->getAvailableSessions(),
        ]);
    }

    #[Route('/register/{id}', name: 'employee_formation_register', methods: ['POST'])]
    public function register(int $id): Response
    {
        $userId = $this->getUser()->getId();

        if ($this->participationService->registerEmployee($userId, $id)) {
            $this->addFlash('success', 'Inscription confirmée.');
        } else {
            $this->addFlash('error', 'Erreur lors de l\'inscription.');
        }

        return $this->redirectToRoute('employee_formation_available');
    }
}