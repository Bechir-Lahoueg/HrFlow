<?php

namespace App\Controller;

use App\Repository\Rh\EmployeeRepository;
use App\Service\Formation\FormationService;
use App\Service\Formation\ParticipationService;
use App\Service\Rh\LeaveBalanceService;
use App\Service\Rh\LeaveRequestService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;


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
    public function rh(
        EmployeeRepository $employeeRepository,
        LeaveRequestService $leaveRequestService,
        FormationService $formationService,
        ChartBuilderInterface $chartBuilder
    ): Response
    {
        $rhId = (int) $this->getUser()?->getId();

        $employeeCount = 0;
        $pendingLeaveCount = 0;

        try {
            $employeeCount = $employeeRepository->count(['rhId' => $rhId]);
            $pendingLeaveCount = $leaveRequestService->getRhPendingCount($rhId);
        } catch (\Throwable) {
        }

        $metrics = $formationService->getRhDashboardMetrics($rhId, 6);

        $categoryColorMap = [
            'Technique' => [
                'background' => '#dbeafe',
                'border' => '#93c5fd'
            ],
            'Soft Skills' => [
                'background' => '#fce7f3',
                'border' => '#f9a8d4'
            ],
            'Management' => [
                'background' => '#ede9fe',
                'border' => '#c4b5fd'
            ],
            'Leadership' => [
                'background' => '#cffafe',
                'border' => '#67e8f9'
            ],
            'Langues' => [
                'background' => '#ffedd5',
                'border' => '#fdba74'
            ],
            'Qualité' => [
                'background' => '#fef9c3',
                'border' => '#fde047'
            ],
            'Sécurité' => [
                'background' => '#fee2e2',
                'border' => '#fca5a5'
            ],
            'Conformité' => [
                'background' => '#e0e7ff',
                'border' => '#a5b4fc'
            ],
            'Bureautique' => [
                'background' => '#f3e8ff',
                'border' => '#d8b4fe'
            ],
            'Finance' => [
                'background' => '#dcfce7',
                'border' => '#86efac'
            ],
            'RH' => [
                'background' => '#e0f2fe',
                'border' => '#7dd3fc'
            ],
            'Digital' => [
                'background' => '#ccfbf1',
                'border' => '#5eead4'
            ],
            'Autre' => [
                'background' => '#f3f4f6',
                'border' => '#d1d5db'
            ],
        ];

        $categoryCounts = $metrics['formations_by_category'] ?? [];
        $categoryLabels = array_keys($categoryCounts);
        $categoryValues = array_values($categoryCounts);
        $backgroundColors = [];
        $borderColors = [];

        foreach ($categoryLabels as $label) {
            $backgroundColors[] = $categoryColorMap[$label]['background'] ?? '#145EB7';
            $borderColors[] = $categoryColorMap[$label]['border'] ?? '#145EB7';
        }
        if ($categoryLabels === []) {
            $categoryLabels = ['Aucune categorie'];
            $categoryValues = [0];
            $categoryColors = ['#D0D0D0'];
        }

        $formationCountChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $formationCountChart->setData([
            'labels' => $categoryLabels,
            'datasets' => [[
                'label' => 'Formations par categorie',
                'data' => $categoryValues,
                'backgroundColor' => $backgroundColors,
                'borderColor' => $borderColors,
                'borderWidth' => 2,
            ]],
        ]);
        $formationCountChart->setOptions([
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
            'cutout' => '64%',
        ]);

        $statusCounts = $metrics['participation_status_counts'] ?? [];
        $accepted = (int) ($statusCounts['accepted'] ?? 0);
        $refused = (int) ($statusCounts['refused'] ?? 0);
        $pending = (int) ($statusCounts['pending'] ?? 0);

        $participationRateChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $participationRateChart->setData([
            'labels' => ['Acceptees', 'Refusees', 'En attente'],
            'datasets' => [[
                'label' => 'Statut des participations',
                'data' => [$accepted, $refused, $pending],
                'backgroundColor' => ['#145EB7', '#dc2626', '#f59e0b'],
                'borderWidth' => 0,
            ]],
        ]);
        $participationRateChart->setOptions([
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
            'cutout' => '68%',
        ]);

        $formationsByMonth = $metrics['formations_by_month'] ?? [];
        $formationByMonthChart = $chartBuilder->createChart(Chart::TYPE_LINE);
        $formationByMonthChart->setData([
            'labels' => array_keys($formationsByMonth),
            'datasets' => [[
                'label' => 'Formations creees',
                'data' => array_values($formationsByMonth),
                'borderColor' => '#3FA9F5',
                'backgroundColor' => 'rgba(63, 169, 245, 0.18)',
                'fill' => true,
                'tension' => 0.35,
                'pointRadius' => 4,
                'pointBackgroundColor' => '#145EB7',
            ]],
        ]);
        $formationByMonthChart->setOptions([
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
        ]);

        return $this->render('DashboardHr/welcome_rh.html.twig', [
            'user' => $this->getUser(),
            'employeeCount' => $employeeCount,
            'pendingLeaveCount' => $pendingLeaveCount,
            'formationMetrics' => $metrics,
            'formationCountChart' => $formationCountChart,
            'participationRateChart' => $participationRateChart,
            'formationByMonthChart' => $formationByMonthChart,
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
