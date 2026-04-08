<?php

namespace App\Controller\employer;

use App\Service\FormationService;
use App\Service\ParticipationService;
use App\Service\SessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/employee/formation')]
final class EmployeeFormationController extends AbstractController
{
    public function __construct(
        private readonly SessionService $sessionService,
        private readonly ParticipationService $participationService,
        private readonly FormationService $formationService,
    ) {
    }

    #[Route('/', name: 'employee_formation_index')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        $rhId = method_exists($user, 'getRhId') ? $user->getRhId() : null;

        $search = $request->query->get('search', '');
        $type = $request->query->get('type', '');
        $sortQuery = $request->query->get('sort', 'created_at-DESC');

        $sortParts = explode('-', $sortQuery);
        $sort = $sortParts[0] ?? 'created_at';
        $dir = $sortParts[1] ?? 'DESC';

        $formations = $rhId ? $this->formationService->getFormationsByRhId($rhId, $search, $type, $sort, $dir) : [];

        return $this->render('DashboardEmployee/formation/formation_index.html.twig', [
            'formations' => $formations,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'sort' => $sortQuery,
            ],
        ]);
    }

    #[Route('/my-requests', name: 'employee_formation_requests')]
    public function myRequests(): Response
    {
        $userId = $this->getUser()->getId();
        $participations = $this->participationService->getEmployeeParticipations($userId);

        $attendanceMap = [];
        foreach ($participations as $p) {
            $attendanceMap[$p->getId()] = $this->participationService->getAttendancePercentage($p->getId());
        }

        return $this->render('DashboardEmployee/formation/formation_requests.html.twig', [
            'participations' => $participations,
            'attendanceMap' => $attendanceMap,
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
            $mySessionStats[$participation->getSession()->getId()] = [
                'statut' => $participation->getStatutParticipation(),
                'pourcentage' => $this->participationService->getAttendancePercentage($participation->getId()),
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

        return $this->redirectToRoute('employee_formation_sessions', ['id' => $this->sessionService->getIdFormationBySessionId($id)]);
    }
}
