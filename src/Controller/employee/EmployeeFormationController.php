<?php

namespace App\Controller\employee;

use App\Service\FormationService;
use App\Service\ParticipationService;
use App\Service\SessionFeedbackService;
use App\Service\SessionService;
use App\Repository\Formation\EmployeeNotificationRepository;
use App\Repository\Formation\SessionFeedbackRepository;
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
        private readonly SessionFeedbackService $sessionFeedbackService,
        private readonly SessionFeedbackRepository $sessionFeedbackRepository,
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
        $formationIds = array_map(static fn($f) => (int) $f->getId(), $formations);

        return $this->render('DashboardEmployee/formation/formation_index.html.twig', [
            'formations' => $formations,
            'ratingMap' => $this->sessionFeedbackService->getAverageRatingsByFormationIds($formationIds),
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
    public function sessions(string $id): Response
    {
        $idInt = (int) $id;
        $formation = $this->formationService->getFormationById($idInt);
        if (!$formation) {
            throw $this->createNotFoundException('Formation non trouvée');
        }

        $sessions = $this->sessionService->getSessionsByFormation($idInt);
        $userId = $this->getUser()->getId();
        $myParticipations = $this->participationService->getEmployeeParticipations($userId);

        $mySessionStats = [];
        foreach ($myParticipations as $participation) {
            $mySessionStats[$participation->getSession()->getId()] = [
                'statut' => $participation->getStatutParticipation(),
                'pourcentage' => $this->participationService->getAttendancePercentage($participation->getId()),
            ];
        }

        $feedbackGivenBySession = [];
        foreach ($myParticipations as $participation) {
            $sessionId = (int) $participation->getSession()->getId();
            $feedbackGivenBySession[$sessionId] = $this->sessionFeedbackRepository->hasFeedbackForSessionAndUser($sessionId, $userId);
        }

        return $this->render('DashboardEmployee/formation/formation_sessions.html.twig', [
            'formation' => $formation,
            'sessions' => $sessions,
            'mySessionStats' => $mySessionStats,
            'feedbackGivenBySession' => $feedbackGivenBySession,
        ]);
    }

    #[Route('/{id}/feedback', name: 'employee_formation_feedback_submit', methods: ['POST'])]
    public function submitFeedback(string $id, Request $request): Response
    {
        $sessionId = (int) $id;
        if (!$this->isCsrfTokenValid('feedback-session-' . $sessionId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide, veuillez reessayer.');
            return $this->redirectToRoute('employee_formation_index');
        }

        $result = $this->sessionFeedbackService->submitFeedback(
            $this->getUser()->getId(),
            $sessionId,
            (int) $request->request->get('rating', 0),
            (string) $request->request->get('comment', ''),
            (bool) $request->request->get('is_anonymous', false)
        );

        $this->addFlash($result['ok'] ? 'success' : 'error', $result['message']);

        $formationId = $this->sessionService->getIdFormationBySessionId($sessionId);

        return $this->redirectToRoute('employee_formation_sessions', ['id' => $formationId]);
    }

    #[Route('/{id}/register', name: 'employee_formation_register', methods: ['POST'])]
    public function register(string $id): Response
    {
        $idInt = (int) $id;
        $userId = $this->getUser()->getId();

        $result = $this->participationService->registerEmployeeDetailed($userId, $idInt);

        if ($result['ok']) {
            $this->addFlash('success', 'Inscription confirmée.');
        } else {
            $reasons = $result['reasons'] ?? [];
            if (count($reasons) > 1) {
                $this->addFlash('error', "Impossible de s'inscrire pour les raisons suivantes :\n\n" . implode("\n", $reasons));
            } elseif (count($reasons) === 1) {
                $this->addFlash('error', $reasons[0]);
            } else {
                $this->addFlash('error', "Impossible de s'inscrire.");
            }
        }

        return $this->redirectToRoute('employee_formation_sessions', ['id' => $this->sessionService->getIdFormationBySessionId($idInt)]);
    }

    #[Route('/notifications/mark-all-read', name: 'employee_notifications_mark_all_read', methods: ['POST'])]
    public function markAllNotificationsRead(Request $request, EmployeeNotificationRepository $notificationRepository): Response
    {
        if ($this->isCsrfTokenValid('employee-notifications-mark-all-read', (string) $request->request->get('_token'))) {
            $notificationRepository->markAllAsReadByEmployee($this->getUser()->getId());
        }

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('employee_formation_index'));
    }

    #[Route('/notifications/{id}/open', name: 'employee_notification_open', methods: ['GET'])]
    public function openNotification(string $id, EmployeeNotificationRepository $notificationRepository): Response
    {
        $notification = $notificationRepository->findOneByIdAndEmployee((int) $id, $this->getUser()->getId());
        if (!$notification) {
            return $this->redirectToRoute('employee_formation_index');
        }

        $notificationRepository->markAsRead($notification);

        $referenceType = $notification->getReferenceType();
        $referenceId = $notification->getReferenceId();

        if ($referenceType === 'session' && $referenceId) {
            $formationId = $this->sessionService->getIdFormationBySessionId($referenceId);
            if ($formationId) {
                return $this->redirectToRoute('employee_formation_sessions', ['id' => $formationId]);
            }
        }

        if ($referenceId) {
            return $this->redirectToRoute('employee_formation_sessions', ['id' => $referenceId]);
        }

        return $this->redirectToRoute('employee_formation_index');
    }

    #[Route('/notifications/history', name: 'employee_notifications_history', methods: ['GET'])]
    public function notificationsHistory(EmployeeNotificationRepository $notificationRepository): Response
    {
        $employeeId = $this->getUser()->getId();

        return $this->render('DashboardEmployee/formation/notifications_history.html.twig', [
            'notifications' => $notificationRepository->findAllByEmployee($employeeId, 200),
            'unreadCount' => $notificationRepository->countUnreadByEmployee($employeeId),
        ]);
    }

    #[Route('/{id}/feedbacks', name: 'employee_formation_feedbacks', methods: ['GET'])]
    public function feedbacks(string $id): Response
    {
        $formationId = (int) $id;
        $formation = $this->formationService->getFormationById($formationId);
        if (!$formation) {
            throw $this->createNotFoundException('Formation non trouvee');
        }

        $feedbacks = $this->sessionFeedbackService->getFeedbacksByFormation($formationId);

        return $this->render('DashboardEmployee/formation/formation_feedbacks.html.twig', [
            'formation' => $formation,
            'feedbacks' => $feedbacks,
        ]);
    }
}
