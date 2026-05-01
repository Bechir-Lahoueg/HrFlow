<?php

namespace App\Controller\employee;

use App\Service\Formation\FormationService;
use App\Service\Formation\ParticipationService;
use App\Service\Formation\CertificateService;
use App\Service\Formation\SessionFeedbackService;
use App\Service\Formation\SessionService;
use App\Service\Shared\HrFlowMailer;
use App\Repository\Formation\EmployeeNotificationRepository;
use App\Repository\Formation\ParticipationFormationRepository;
use App\Repository\Formation\PresenceFormationRepository;
use App\Repository\Formation\SessionFeedbackRepository;
use App\Repository\Rh\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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
        private readonly CertificateService $certificateService,
        private readonly ParticipationFormationRepository $participationRepository,
        private readonly SessionFeedbackService $sessionFeedbackService,
        private readonly SessionFeedbackRepository $sessionFeedbackRepository,
        private readonly PresenceFormationRepository $presenceFormationRepository,
        private readonly UserRepository $userRepository,
        private readonly HrFlowMailer $hrFlowMailer,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/', name: 'employee_formation_index')]
    public function index(Request $request): Response
    {
        $search = (string) $request->query->get('search', '');
        $type = (string) $request->query->get('type', '');
        $organisme = trim((string) $request->query->get('organisme', ''));
        $sortQuery = (string) $request->query->get('sort', 'created_at-DESC');

        $sortParts = explode('-', $sortQuery, 2);
        $sort = $sortParts[0];
        $dir = $sortParts[1] ?? 'DESC';

        $formations = $this->formationService->getAllFormations($search, $type, $sort, $dir, $organisme);
        $formationIds = array_map(static fn($f) => (int) $f->getId(), $formations);
        $ratingMap = $this->sessionFeedbackService->getAverageRatingsByFormationIds($formationIds);

        // Build top insights for employees from the global catalog.
        $allFormations = $this->formationService->getAllFormations();
        $allFormationIds = array_map(static fn($f) => (int) $f->getId(), $allFormations);
        $allRatingMap = $this->sessionFeedbackService->getAverageRatingsByFormationIds($allFormationIds);

        $topFormationRows = [];
        foreach ($allFormations as $formation) {
            $formationId = (int) $formation->getId();
            $rating = $allRatingMap[$formationId] ?? ['average' => 0.0, 'count' => 0];

            $topFormationRows[] = [
                'formation' => $formation,
                'average' => (float) ($rating['average'] ?? 0.0),
                'count' => (int) ($rating['count'] ?? 0),
            ];
        }

        usort($topFormationRows, static function (array $a, array $b): int {
            $cmpAverage = $b['average'] <=> $a['average'];
            if ($cmpAverage !== 0) {
                return $cmpAverage;
            }

            $cmpCount = $b['count'] <=> $a['count'];
            if ($cmpCount !== 0) {
                return $cmpCount;
            }

            return strcmp((string) $a['formation']->getTitre(), (string) $b['formation']->getTitre());
        });

        $topFormateursMap = [];
        foreach ($allFormations as $formation) {
            $formationId = (int) $formation->getId();
            $org = trim((string) $formation->getOrganisme());
            if ($org === '') {
                continue;
            }

            if (!isset($topFormateursMap[$org])) {
                $topFormateursMap[$org] = [
                    'organisme' => $org,
                    'formationsCount' => 0,
                    'ratingWeightedTotal' => 0.0,
                    'feedbackCount' => 0,
                ];
            }

            $topFormateursMap[$org]['formationsCount']++;

            $rating = $allRatingMap[$formationId] ?? null;
            $count = (int) ($rating['count'] ?? 0);
            if ($count > 0) {
                $average = (float) ($rating['average'] ?? 0.0);
                $topFormateursMap[$org]['feedbackCount'] += $count;
                $topFormateursMap[$org]['ratingWeightedTotal'] += $average * $count;
            }
        }

        $topFormateurs = [];
        foreach ($topFormateursMap as $row) {
            $feedbackCount = (int) $row['feedbackCount'];
            $average = $feedbackCount > 0 ? round((float) $row['ratingWeightedTotal'] / $feedbackCount, 1) : 0.0;

            $topFormateurs[] = [
                'organisme' => $row['organisme'],
                'formationsCount' => (int) $row['formationsCount'],
                'feedbackCount' => $feedbackCount,
                'averageRating' => $average,
            ];
        }

        usort($topFormateurs, static function (array $a, array $b): int {
            $cmpFormations = $b['formationsCount'] <=> $a['formationsCount'];
            if ($cmpFormations !== 0) {
                return $cmpFormations;
            }

            $cmpRating = $b['averageRating'] <=> $a['averageRating'];
            if ($cmpRating !== 0) {
                return $cmpRating;
            }

            return strcmp((string) $a['organisme'], (string) $b['organisme']);
        });

        $rhIds = array_values(array_unique(array_filter(array_map(
            static fn($f) => (int) $f->getRhId(),
            $formations
        ), static fn(int $id): bool => $id > 0)));
        $rhNames = [];
        if ($rhIds !== []) {
            foreach ($this->userRepository->findBy(['id' => $rhIds]) as $rhUser) {
                $rhNames[(int) $rhUser->getId()] = $rhUser->getUsername() ?: ('RH #' . $rhUser->getId());
            }
        }

        $weekStart = new \DateTimeImmutable('monday this week');
        $weekEnd = $weekStart->modify('+6 days');
        $weeklyRows = $this->presenceFormationRepository->countWeeklyAttendanceByEmployee(
            $this->requireUserId(),
            $weekStart,
            $weekEnd
        );

        $attendanceByDate = [];
        foreach ($weeklyRows as $row) {
            $presenceDate = $row['presenceDate'];
            $attendanceByDate[$presenceDate->format('Y-m-d')] = (int) $row['attendanceCount'];
        }

        $todayKey = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $dayLabels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
        $fullDayLabels = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        $weeklyDays = [];
        $maxValue = 0;
        $totalTrainingDays = 0;
        $bestDayLabel = null;
        $bestDayValue = 0;

        for ($index = 0; $index < 7; $index++) {
            $date = $weekStart->modify('+' . $index . ' days');
            $dateKey = $date->format('Y-m-d');
            $value = (int) ($attendanceByDate[$dateKey] ?? 0);
            $isToday = $dateKey === $todayKey;

            if ($value > 0) {
                $totalTrainingDays++;
            }

            if ($value > $bestDayValue) {
                $bestDayValue = $value;
                $bestDayLabel = $fullDayLabels[$index];
            }

            $maxValue = max($maxValue, $value);

            $weeklyDays[] = [
                'label' => $dayLabels[$index],
                'date' => $date,
                'value' => $value,
                'isToday' => $isToday,
            ];
        }

        $maxValue = max(1, $maxValue);
        foreach ($weeklyDays as &$day) {
            $day['heightPercent'] = $day['value'] > 0 ? max(14, (int) round(($day['value'] / $maxValue) * 100)) : 8;
        }
        unset($day);

        $weeklyTraining = [
            'days' => $weeklyDays,
            'totalTrainingDays' => $totalTrainingDays,
            'bestDayLabel' => $bestDayLabel,
            'bestDayValue' => $bestDayValue,
            'weekRangeLabel' => sprintf('%s - %s', $weekStart->format('d/m'), $weekEnd->format('d/m')),
        ];

        return $this->render('DashboardEmployee/formation/formation_index.html.twig', [
            'formations' => $formations,
            'ratingMap' => $ratingMap,
            'rhNames' => $rhNames,
            'weeklyTraining' => $weeklyTraining,
            'topFormations' => array_slice($topFormationRows, 0, 3),
            'topFormateurs' => array_slice($topFormateurs, 0, 3),
            'filters' => [
                'search' => $search,
                'type' => $type,
                'organisme' => $organisme,
                'sort' => $sortQuery,
            ],
        ]);
    }

    #[Route('/top-formateur/{organisme}', name: 'employee_top_formateur_formations', methods: ['GET'])]
    public function topFormateurFormations(Request $request, string $organisme): Response
    {
        $organisme = trim($organisme);
        if ($organisme === '') {
            return $this->redirectToRoute('employee_formation_index');
        }

        $sort = (string) $request->query->get('sort', 'rating_desc');
        $allowedSorts = ['rating_desc', 'rating_asc', 'title_asc', 'title_desc', 'duration_asc', 'duration_desc'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'rating_desc';
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 9;

        $formations = $this->formationService->getAllFormations('', '', 'created_at', 'DESC', $organisme);
        $formationIds = array_map(static fn($f) => (int) $f->getId(), $formations);
        $ratingMap = $this->sessionFeedbackService->getAverageRatingsByFormationIds($formationIds);

        usort($formations, static function ($a, $b) use ($ratingMap, $sort): int {
            $aId = (int) $a->getId();
            $bId = (int) $b->getId();

            $aAvg = (float) ($ratingMap[$aId]['average'] ?? 0.0);
            $bAvg = (float) ($ratingMap[$bId]['average'] ?? 0.0);
            $aCount = (int) ($ratingMap[$aId]['count'] ?? 0);
            $bCount = (int) ($ratingMap[$bId]['count'] ?? 0);

            return match ($sort) {
                'rating_asc' => ($aAvg <=> $bAvg) ?: ($aCount <=> $bCount) ?: strcmp((string) $a->getTitre(), (string) $b->getTitre()),
                'title_asc' => strcmp((string) $a->getTitre(), (string) $b->getTitre()),
                'title_desc' => strcmp((string) $b->getTitre(), (string) $a->getTitre()),
                'duration_asc' => ((int) $a->getDuree() <=> (int) $b->getDuree()) ?: strcmp((string) $a->getTitre(), (string) $b->getTitre()),
                'duration_desc' => ((int) $b->getDuree() <=> (int) $a->getDuree()) ?: strcmp((string) $a->getTitre(), (string) $b->getTitre()),
                default => ($bAvg <=> $aAvg) ?: ($bCount <=> $aCount) ?: strcmp((string) $a->getTitre(), (string) $b->getTitre()),
            };
        });

        $totalFormations = count($formations);
        $totalDuration = array_reduce($formations, static fn(int $carry, $formation): int => $carry + (int) $formation->getDuree(), 0);
        $totalPages = max(1, (int) ceil($totalFormations / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $formationsPage = array_slice($formations, $offset, $perPage);

        $rhIds = array_values(array_unique(array_filter(array_map(
            static fn($f) => (int) $f->getRhId(),
            $formationsPage
        ), static fn(int $id): bool => $id > 0)));
        $rhNames = [];
        if ($rhIds !== []) {
            foreach ($this->userRepository->findBy(['id' => $rhIds]) as $rhUser) {
                $rhNames[(int) $rhUser->getId()] = $rhUser->getUsername() ?: ('RH #' . $rhUser->getId());
            }
        }

        return $this->render('DashboardEmployee/formation/formation_top_formateur.html.twig', [
            'organisme' => $organisme,
            'formations' => $formationsPage,
            'ratingMap' => $ratingMap,
            'rhNames' => $rhNames,
            'sort' => $sort,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalFormations' => $totalFormations,
            'totalDuration' => $totalDuration,
        ]);
    }

    #[Route('/my-requests', name: 'employee_formation_requests')]
    public function myRequests(): Response
    {
        $userId = $this->requireUserId();
        $participations = $this->participationService->getEmployeeParticipations($userId);

        $attendanceMap = [];
        foreach ($participations as $p) {
            $participationId = $p->getId();
            if ($participationId === null) {
                continue;
            }
            $attendanceMap[$participationId] = $this->participationService->getAttendancePercentage($participationId);
        }

        return $this->render('DashboardEmployee/formation/formation_requests.html.twig', [
            'participations' => $participations,
            'attendanceMap' => $attendanceMap,
        ]);
    }

 #[Route('/my-certificates', name: 'employee_formation_certificates', methods: ['GET'])]
 public function myCertificates(Request $request): Response // Ajoute l'argument Request
 {
     $userId = $this->requireUserId();
     $participations = $this->participationService->getEmployeeParticipations($userId);

     // 1. Récupérer le paramètre de tri (par défaut 'date-DESC')
     $sort = (string) $request->query->get('sort', 'date-DESC');

     $certificateRows = [];
     foreach ($participations as $participation) {
         $session = $participation->getSession();
         $formation = $session?->getFormation();
         if (!$session || !$formation) {
             continue;
         }

         $attendance = $this->participationService->getAttendancePercentage((int) $participation->getId());
         $isEligible = $session->getStatut() === 'Terminee' && $attendance >= 80;
         if (!$isEligible) {
             continue;
         }

         $certificateRows[] = [
             'participation' => $participation,
             'session' => $session,
             'formation' => $formation,
             'attendance' => $attendance,
             'isEligible' => true,
         ];
     }

     // 2. Appliquer le tri selon le paramètre
     usort($certificateRows, static function (array $a, array $b) use ($sort): int {
         if (str_starts_with($sort, 'date-')) {
             $aDate = $a['session']->getDateFin();
             $bDate = $b['session']->getDateFin();
             $cmp = ($aDate?->getTimestamp() ?? 0) <=> ($bDate?->getTimestamp() ?? 0);
             return str_ends_with($sort, 'DESC') ? -$cmp : $cmp;
         }

         if (str_starts_with($sort, 'titre-')) {
             $cmp = strcmp((string) $a['formation']->getTitre(), (string) $b['formation']->getTitre());
             return str_ends_with($sort, 'DESC') ? -$cmp : $cmp;
         }

         return 0;
     });

     return $this->render('DashboardEmployee/formation/formation_certificates.html.twig', [
         'certificateRows' => $certificateRows,
         // 3. ENVOYER LA VARIABLE FILTERS ICI :
         'filters' => [
             'sort' => $sort,
         ],
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
        $userId = $this->requireUserId();
        $myParticipations = $this->participationService->getEmployeeParticipations($userId);

        $mySessionStats = [];
        foreach ($myParticipations as $participation) {
            $session = $participation->getSession();
            if ($session === null || $participation->getId() === null || $session->getId() === null) {
                continue;
            }
            $mySessionStats[$session->getId()] = [
                'statut' => $participation->getStatutParticipation(),
                'pourcentage' => $this->participationService->getAttendancePercentage($participation->getId()),
            ];
        }

        $feedbackGivenBySession = [];
        foreach ($myParticipations as $participation) {
            $session = $participation->getSession();
            if ($session === null || $session->getId() === null) {
                continue;
            }
            $sessionId = (int) $session->getId();
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

        $userId = $this->requireUserId();
        $result = $this->sessionFeedbackService->submitFeedback(
            $userId,
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
        $userId = $this->requireUserId();

        $result = $this->participationService->registerEmployeeDetailed($userId, $idInt);

        if ($result['ok']) {
            $this->addFlash('success', 'Inscription confirmée.');
        } else {
            $reasons = $result['reasons'];
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

    #[Route('/participation/{id}/certificate', name: 'employee_formation_certificate_download', methods: ['GET'])]
    public function downloadCertificate(string $id): Response
    {
        $participationId = (int) $id;
        $employeeId = $this->requireUserId();

        $participation = $this->participationRepository->find($participationId);
        if (!$participation || (int) $participation->getEmployee()?->getId() !== $employeeId) {
            throw $this->createNotFoundException('Participation introuvable.');
        }

        $session = $participation->getSession();
        if (!$session || $session->getStatut() !== 'Terminee') {
            $this->addFlash('error', 'Certificat indisponible : la formation n est pas encore terminee.');
            return $this->redirectToRoute('employee_formation_requests');
        }

        $dateDebut = $session->getDateDebut();
        $dateFin = $session->getDateFin();
        if ($dateDebut === null || $dateFin === null) {
            $this->addFlash('error', 'Certificat indisponible : dates de session invalides.');
            return $this->redirectToRoute('employee_formation_requests');
        }

        $rhCreatorName = null;
        $formation = $session->getFormation();
        if ($formation && $formation->getRhId()) {
            $rhCreatorName = $this->userRepository->find($formation->getRhId())?->getUsername();
        }

        $organisme = trim((string) ($formation?->getOrganisme() ?? ''));
        if ($organisme === '') {
            $organisme = 'HrFlow';
        }

        $token = $participation->getToken();
        if ($token === null || $token === '') {
            $token = bin2hex(random_bytes(16));
            $participation->setToken($token);
            $this->em->flush();
        }

        $user = $this->getUser();
        $certificate = $this->certificateService->generateCertificate(
            $participation->getEmployee()?->getFullName() ?? ($user?->getUserIdentifier() ?? 'Employe'),
            $formation?->getTitre() ?? 'Formation',
            $dateDebut,
            $dateFin,
            $organisme,
            $rhCreatorName,
            $token
        );

        if (!$participation->isCertificatObtenu()) {
            $participation->setCertificatObtenu(true);
            $this->em->flush();
            $this->hrFlowMailer->sendCertificateAvailable($participation);
        }

        $response = new Response($certificate['content']);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $certificate['fileName'])
        );

        return $response;
    }

    #[Route('/verify/{token}', name: 'app_verify_certificate', methods: ['GET'])]
    public function verifyCertificate(string $token): Response
    {
        $participation = $this->participationRepository->findOneBy(['token' => $token]);

        if (!$participation) {
            return $this->render('Verification/certificate_verify.html.twig', [
                'isValid' => false,
            ], new Response('', Response::HTTP_NOT_FOUND));
        }

        $session = $participation->getSession();
        $formation = $session?->getFormation();
        $employeeName = $participation->getEmployee()?->getFullName() ?? 'Employe';

        return $this->render('Verification/certificate_verify.html.twig', [
            'isValid' => true,
            'employeeName' => $employeeName,
            'formationTitle' => (string) ($formation?->getTitre() ?? 'Formation'),
            'organisme' => (string) ($formation?->getOrganisme() ?: 'HrFlow'),
            'dateDebut' => $session?->getDateDebut(),
            'dateFin' => $session?->getDateFin(),
            'verifiedAt' => new \DateTime(),
        ]);
    }

    #[Route('/notifications/mark-all-read', name: 'employee_notifications_mark_all_read', methods: ['POST'])]
    public function markAllNotificationsRead(Request $request, EmployeeNotificationRepository $notificationRepository): Response
    {
        if ($this->isCsrfTokenValid('employee-notifications-mark-all-read', (string) $request->request->get('_token'))) {
            $notificationRepository->markAllAsReadByEmployee($this->requireUserId());
        }

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('employee_formation_index'));
    }

    #[Route('/notifications/{id}/open', name: 'employee_notification_open', methods: ['GET'])]
    public function openNotification(string $id, EmployeeNotificationRepository $notificationRepository): Response
    {
        $notification = $notificationRepository->findOneByIdAndEmployee((int) $id, $this->requireUserId());
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
        $employeeId = $this->requireUserId();

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

    private function requireUserId(): int
    {
        $user = $this->getUser();
        if ($user === null || !method_exists($user, 'getId') || $user->getId() === null) {
            throw $this->createAccessDeniedException();
        }

        return (int) $user->getId();
    }
}
