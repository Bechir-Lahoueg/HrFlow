<?php

namespace App\Controller;

use App\Repository\Rh\EmployeeRepository;
use App\Service\Formation\ParticipationService;
use App\Service\Rh\LeaveBalanceService;
use App\Service\Rh\LeaveRequestService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


final class WelcomeController extends AbstractController
{
    #[Route('/welcome', name: 'app_welcome')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_welcome_admin');
        }

        if ($this->isGranted('ROLE_RH')) {
            return $this->redirectToRoute('app_welcome_rh');
        }

        if ($this->isGranted('ROLE_EMPLOYEE')) {
            return $this->redirectToRoute('app_welcome_employee');
        }

        throw $this->createAccessDeniedException('Role not supported for welcome page.');
    }

    #[Route('/welcome/admin', name: 'app_welcome_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(): Response
    {
        return $this->render('DashboardAdmin/welcome_admin.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/welcome/rh', name: 'app_welcome_rh')]
    #[IsGranted('ROLE_RH')]
    public function rh(EmployeeRepository $employeeRepository, LeaveRequestService $leaveRequestService): Response
    {
        $rhId = (int) $this->getUser()?->getId();

        $employeeCount = 0;
        $pendingLeaveCount = 0;

        try {
            $employeeCount = $employeeRepository->count(['rhId' => $rhId]);
            $pendingLeaveCount = $leaveRequestService->getRhPendingCount($rhId);
        } catch (\Throwable) {
        }

        return $this->render('DashboardHr/welcome_rh.html.twig', [
            'user' => $this->getUser(),
            'employeeCount' => $employeeCount,
            'pendingLeaveCount' => $pendingLeaveCount,
        ]);
    }

    #[Route('/welcome/employee', name: 'app_welcome_employee')]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function employee(
        LeaveRequestService $leaveRequestService,
        LeaveBalanceService $leaveBalanceService,
        ParticipationService $participationService
    ): Response {
        $employeeId = (int) $this->getUser()?->getId();

        $pendingLeaveCount = 0;
        $availableLeaveDays = 0.0;
        $balance = ['available_days' => 0.0, 'total_accrued' => 0.0, 'total_used' => 0.0];
        $leaveStats = ['pending_count' => 0, 'approved_count' => 0, 'rejected_count' => 0];
        $trainingCount = 0;
        $certificateCount = 0;

        // Toujours initialiser 12 mois pour afficher le line chart meme si peu de donnees.
        $monthsMap = [];
        for ($i = 11; $i >= 0; $i--) {
            $label = (new \DateTimeImmutable("first day of -$i months"))->format('M Y');
            $monthsMap[$label] = 0;
        }
        $formationsByMonth = $monthsMap;
        $attendanceData = [];

        try {
            $pendingLeaveCount = $leaveRequestService->getEmployeePendingCount($employeeId);
            $balance = $leaveBalanceService->getEmployeeBalance($employeeId);
            $availableLeaveDays = (float) ($balance['available_days'] ?? 0);
            $leaveStats = $leaveRequestService->getEmployeeDashboardStats($employeeId);
        } catch (\Throwable) {
        }

        try {
            $participations = $participationService->getEmployeeParticipations($employeeId);
            $trainingCount = count($participations);
            $certificateCount = count(array_filter($participations, static fn ($p) => $p->isCertificatObtenu()));

            foreach ($participations as $participation) {
                $session = $participation->getSession();
                $dateDebut = $session?->getDateDebut();
                if ($dateDebut) {
                    $label = $dateDebut->format('M Y');
                    if (array_key_exists($label, $formationsByMonth)) {
                        $formationsByMonth[$label]++;
                    }
                }

                $title = $session?->getFormation()?->getTitre();
                if (!$title) {
                    continue;
                }

                $shortTitle = function_exists('mb_strimwidth')
                    ? mb_strimwidth($title, 0, 20, '...')
                    : (strlen($title) > 20 ? substr($title, 0, 17) . '...' : $title);

                $attendanceData[] = [
                    'label' => $shortTitle,
                    'value' => $participationService->getAttendancePercentage((int) $participation->getId()),
                ];
            }
        } catch (\Throwable) {
            // On garde les compteurs/conges meme si les donnees formation echouent.
        }

        return $this->render('DashboardEmployee/welcome_employee.html.twig', [
            'user' => $this->getUser(),
            'pendingLeaveCount' => $pendingLeaveCount,
            'availableLeaveDays' => $availableLeaveDays,
            'balance' => $balance,
            'leaveStats' => $leaveStats,
            'trainingCount' => $trainingCount,
            'certificateCount' => $certificateCount,
            'formationsByMonth' => $formationsByMonth,
            'attendanceData' => $attendanceData,
        ]);
    }
}
