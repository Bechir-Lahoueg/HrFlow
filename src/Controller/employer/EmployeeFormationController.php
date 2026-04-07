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
        $user = $this->getUser();
        $rhId = method_exists($user, 'getRhId') ? $user->getRhId() : null;

        $formations = $rhId ? $this->formationService->getFormationsByRhId($rhId) : [];

        return $this->render('DashboardEmployee/formation/formation_index.html.twig', [
            'formations' => $formations,
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

        $mySessionStats = [];
        foreach ($myParticipations as $participation) {
            $mySessionStats[$participation['id_session']] = [
                'statut' => $participation['statut_participation'],
                'pourcentage' => $participation['pourcentage_presence'] ?? 0
            ];
        }

        return $this->render('DashboardEmployee/formation/formation_sessions.html.twig', [
            'formation' => $formation,
            'sessions' => $sessions,
            'mySessionStats' => $mySessionStats,
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