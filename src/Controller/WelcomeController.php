<?php

namespace App\Controller;

use App\Service\Shared\ExternalApiService;
use App\Repository\Rh\EmployeeRepository;
use App\Service\Formation\FormationService;
use App\Service\Rh\LeaveRequestService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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

        throw $this->createAccessDeniedException('Role not supported.');
    }

    #[Route('/welcome/admin', name: 'app_welcome_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(Request $request, ExternalApiService $api): Response
    {
        $refresh = $request->query->get('refresh');
        $all = $refresh === 'all';

        return $this->render('DashboardAdmin/welcome_admin.html.twig', [
            'user' => $this->getUser(),
            'weather' => $api->getWeatherTunis($all || $refresh === 'weather'),
            'forecast' => $api->getForecastTunis($all || $refresh === 'forecast'),
            'rates' => $api->getExchangeRates($all || $refresh === 'rates'),
            'news' => $api->getBusinessNews('économie entreprise', $all || $refresh === 'news'),
            'holidays' => $api->getUpcomingHolidaysTN($all || $refresh === 'holidays'),
            'quote' => $api->getDailyQuote('business', $all || $refresh === 'quote'),
            'serverTime' => new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis')),
        ]);
    }

    #[Route('/welcome/rh', name: 'app_welcome_rh')]
    #[IsGranted('ROLE_RH')]
    public function rh(
        Request $request,
        ExternalApiService $api,
        EmployeeRepository $employeeRepository,
        LeaveRequestService $leaveRequestService,
        FormationService $formationService,
        ChartBuilderInterface $chartBuilder
    ): Response
    {
        $refresh = $request->query->get('refresh');
        $all = $refresh === 'all';

        // FIX: get RH ID safely
        $user = $this->getUser();
        $rhId = method_exists($user, 'getId') ? $user->getId() : null;

        // Metrics
        $metrics = $formationService->getRhDashboardMetrics($rhId, 6);

        // FIX: missing variables
        $employeeCount = $employeeRepository->count([]);
        $pendingLeaveCount = $leaveRequestService->countPendingRequests();

        // Charts
        $categoryCounts = $metrics['formations_by_category'] ?? [];

        $formationCountChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $formationCountChart->setData([
            'labels' => array_keys($categoryCounts),
            'datasets' => [[
                'data' => array_values($categoryCounts),
            ]],
        ]);

        $statusCounts = $metrics['participation_status_counts'] ?? [];

        $participationRateChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $participationRateChart->setData([
            'labels' => ['Acceptées', 'Refusées', 'En attente'],
            'datasets' => [[
                'data' => [
                    $statusCounts['accepted'] ?? 0,
                    $statusCounts['refused'] ?? 0,
                    $statusCounts['pending'] ?? 0,
                ],
            ]],
        ]);

        $formationsByMonth = $metrics['formations_by_month'] ?? [];

        $formationByMonthChart = $chartBuilder->createChart(Chart::TYPE_LINE);
        $formationByMonthChart->setData([
            'labels' => array_keys($formationsByMonth),
            'datasets' => [[
                'label' => 'Formations',
                'data' => array_values($formationsByMonth),
            ]],
        ]);

        return $this->render('DashboardHr/welcome_rh.html.twig', [
            'user' => $this->getUser(),

            // RH stats
            'employeeCount' => $employeeCount,
            'pendingLeaveCount' => $pendingLeaveCount,
            'formationMetrics' => $metrics,
            'formationCountChart' => $formationCountChart,
            'participationRateChart' => $participationRateChart,
            'formationByMonthChart' => $formationByMonthChart,

            // APIs (merged from bechir branch)
            'weather' => $api->getWeatherTunis($all || $refresh === 'weather'),
            'forecast' => $api->getForecastTunis($all || $refresh === 'forecast'),
            'holidays' => $api->getUpcomingHolidaysTN($all || $refresh === 'holidays'),
            'news' => $api->getBusinessNews('ressources humaines', $all || $refresh === 'news'),
            'quote' => $api->getDailyQuote('leadership', $all || $refresh === 'quote'),
            'rates' => $api->getExchangeRates($all || $refresh === 'rates'),
            'serverTime' => new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis')),
        ]);
    }

    #[Route('/welcome/employee', name: 'app_welcome_employee')]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function employee(Request $request, ExternalApiService $api): Response
    {
        $refresh = $request->query->get('refresh');
        $all = $refresh === 'all';
        $holidays = $api->getUpcomingHolidaysTN($all || $refresh === 'holidays');

        return $this->render('DashboardEmployee/welcome_employee.html.twig', [
            'user' => $this->getUser(),
            'weather' => $api->getWeatherTunis($all || $refresh === 'weather'),
            'forecast' => $api->getForecastTunis($all || $refresh === 'forecast'),
            'quote' => $api->getDailyQuote('motivational', $all || $refresh === 'quote'),
            'advice' => $api->getAdviceOfTheDay($all || $refresh === 'advice'),
            'nextHoliday' => $holidays[0] ?? null,
            'holidays' => $holidays,
            'news' => $api->getBusinessNews('bien-être travail', $all || $refresh === 'news'),
            'serverTime' => new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis')),
        ]);
    }
}