<?php

namespace App\Controller\employer;

use App\Service\SessionService;
use App\Service\ParticipationService;
use App\Service\FormationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/employee/formation')]
final class EmployeeFormationController extends AbstractController
{
    public function __construct(
        private readonly SessionService $sessionService,
        private readonly ParticipationService $participationService,
        private readonly FormationService $formationService
    ) {}

    #[Route('/', name: 'employee_formation_index')]
    public function index(): Response
    {
        return $this->render('DashboardEmployee/formation/formation_index.html.twig', [
            'formations' => $this->formationService->getAllFormations(),
        ]);
    }

    #[Route('/my-requests', name: 'employee_formation_requests')]
    public function myRequests(): Response
    {
        $userId = $this->getUser()->getId();
        $participations = $this->participationService->getEmployeeParticipations($userId);

        return $this->render('DashboardEmployee/formation/formation_requests.html.twig', [
            'participations' => $participations,
        ]);
    }

    #[Route('/{id}/sessions', name: 'employee_formation_sessions')]
    public function sessions(int $id): Response
    {
        $formation = $this->formationService->getFormationById($id);
        if (!$formation) {
            throw $this->createNotFoundException('Formation non trouvée');
        }

        $sessions = $this->sessionService->getSessionsByFormation($id);
        $userId = $this->getUser()->getId();
        $myParticipations = $this->participationService->getEmployeeParticipations($userId);

        // Créer un map des sessions où l'employé est inscrit
        $mySessionIds = [];
        foreach ($myParticipations as $participation) {
            $mySessionIds[$participation['id_session']] = $participation['statut_participation'];
        }

        return $this->render('DashboardEmployee/formation/formation_sessions.html.twig', [
            'formation' => $formation,
            'sessions' => $sessions,
            'mySessionIds' => $mySessionIds,
        ]);
    }

    #[Route('/{id}/register', name: 'employee_formation_register', methods: ['POST'])]
    public function register(int $id): Response
    {
        $userId = $this->getUser()->getId();

        if ($this->participationService->registerEmployee($userId, $id)) {
            $this->addFlash('success', 'Inscription confirmée.');
        } else {
            $this->addFlash('error', 'Vous êtes déjà inscrit à cette session.');
        }

        return $this->redirectToRoute('employee_formation_sessions', ['id' => $this->sessionService->getFormationIdBySessionId($id)]);
    }
}