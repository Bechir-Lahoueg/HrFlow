<?php

namespace App\Controller;

use App\Service\QuickChartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/charts')]
class ChartsController extends AbstractController
{
    public function __construct(
        private QuickChartService $chartService,
        private EntityManagerInterface $em
    ) {
    }

    #[Route('', name: 'charts_index', methods: ['GET'])]
    public function index(): Response
    {
        // Recruitment-focused charts (smaller size)
        $charts = [
            'applications_by_source' => $this->chartService->createDoughnutChart(
                ['LinkedIn', 'Indeed', 'Site Web', 'Ref.', 'Autre'],
                [45, 30, 35, 20, 10],
                ['title' => 'Sources', 'width' => 280, 'height' => 200, 'showLegend' => false]
            ),
            'hiring_timeline' => $this->chartService->createLineChart(
                ['J', 'F', 'M', 'A', 'M', 'J'],
                [
                    ['label' => 'Postes', 'data' => [8, 12, 10, 15, 11, 9]],
                    ['label' => 'Embauches', 'data' => [3, 5, 4, 7, 6, 5]],
                ],
                ['title' => 'Activité', 'tension' => 0.4, 'width' => 320, 'height' => 200, 'showLegend' => true]
            ),
            'application_status' => $this->chartService->createPieChart(
                ['Nouv.', 'Cours', 'Entr.', 'Offre', 'Emb.', 'Ref.'],
                [50, 80, 35, 20, 15, 40],
                ['title' => 'Statuts', 'width' => 280, 'height' => 200, 'showLegend' => false]
            ),
            'job_offer_performance' => $this->chartService->createBarChart(
                ['Dev', 'Mkt', 'RH', 'Com', 'Des'],
                [
                    ['label' => 'Vues', 'data' => [450, 280, 150, 320, 200]],
                    ['label' => 'Cand.', 'data' => [45, 32, 18, 38, 22]],
                ],
                ['title' => 'Offres', 'width' => 320, 'height' => 200, 'showLegend' => true]
            ),
        ];

        return $this->render('charts/recruitment.html.twig', [
            'charts' => $charts,
        ]);
    }

    #[Route('/dashboard', name: 'charts_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        // Recruitment Dashboard Metrics (compact)
        $metrics = [
            'fill_rate' => $this->chartService->createGaugeChart(78, 100, [
                'title' => 'Remplissage',
                'labels' => ['Remplis', 'Restants'],
                'width' => 200,
                'height' => 120,
            ]),
            'time_to_hire' => $this->chartService->createGaugeChart(18, 30, [
                'title' => 'Temps (j)',
                'labels' => ['Moy', 'Obj'],
                'width' => 200,
                'height' => 120,
            ]),
            'offer_acceptance' => $this->chartService->createGaugeChart(85, 100, [
                'title' => 'Acceptation',
                'labels' => ['Acc', 'Ref'],
                'width' => 200,
                'height' => 120,
            ]),
        ];

        // Recruitment funnel
        $funnel = $this->chartService->createHorizontalBarChart(
            ['Cand.', 'Présél.', 'Entr.', 'Off.', 'Emb.'],
            [
                ['label' => 'Cand.', 'data' => [320, 180, 75, 35, 28]],
            ],
            ['title' => 'Entonnoir', 'width' => 350, 'height' => 200, 'showLegend' => false]
        );

        // Monthly applications vs hires
        $hiringTrend = $this->chartService->createLineChart(
            ['J', 'F', 'M', 'A', 'M', 'J'],
            [
                ['label' => 'Cand.', 'data' => [45, 52, 48, 65, 58, 72]],
                ['label' => 'Emb.', 'data' => [3, 4, 3, 6, 5, 7]],
            ],
            ['title' => 'Tendances', 'tension' => 0.4, 'width' => 350, 'height' => 200]
        );

        // Application sources
        $sources = $this->chartService->createPieChart(
            ['LI', 'Ind', 'Web', 'Ref', 'Agc', 'Aut'],
            [85, 62, 45, 28, 15, 10],
            ['title' => 'Sources', 'width' => 280, 'height' => 200, 'showLegend' => false]
        );

        // Recruiter performance
        $recruiterPerf = $this->chartService->createBarChart(
            ['S', 'T', 'M', 'L', 'E'],
            [
                ['label' => 'Prés.', 'data' => [25, 32, 28, 35, 30]],
                ['label' => 'Emb.', 'data' => [5, 7, 6, 8, 6]],
            ],
            ['title' => 'Recruteurs', 'width' => 350, 'height' => 200, 'showLegend' => true]
        );

        return $this->render('charts/recruitment_dashboard.html.twig', [
            'metrics' => $metrics,
            'funnel' => $funnel,
            'hiring_trend' => $hiringTrend,
            'sources' => $sources,
            'recruiter_perf' => $recruiterPerf,
        ]);
    }

    #[Route('/generate', name: 'charts_generate', methods: ['POST'])]
    public function generateChart(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $chartType = $data['type'] ?? 'bar';
            $options = $data['options'] ?? [];

            $chartUrl = match ($chartType) {
                'bar' => $this->chartService->createBarChart(
                    $data['labels'] ?? [],
                    $data['datasets'] ?? [],
                    $options
                ),
                'line' => $this->chartService->createLineChart(
                    $data['labels'] ?? [],
                    $data['datasets'] ?? [],
                    $options
                ),
                'pie' => $this->chartService->createPieChart(
                    $data['labels'] ?? [],
                    $data['data'] ?? [],
                    $options
                ),
                'doughnut' => $this->chartService->createDoughnutChart(
                    $data['labels'] ?? [],
                    $data['data'] ?? [],
                    $options
                ),
                'radar' => $this->chartService->createRadarChart(
                    $data['labels'] ?? [],
                    $data['datasets'] ?? [],
                    $options
                ),
                'polarArea' => $this->chartService->createPolarAreaChart(
                    $data['labels'] ?? [],
                    $data['data'] ?? [],
                    $options
                ),
                'gauge' => $this->chartService->createGaugeChart(
                    $data['value'] ?? 0,
                    $data['max'] ?? 100,
                    $options
                ),
                default => throw new \InvalidArgumentException('Invalid chart type: ' . $chartType),
            };

            return new JsonResponse(['url' => $chartUrl]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/recruitment-sources', name: 'charts_api_sources', methods: ['GET'])]
    public function recruitmentSources(): JsonResponse
    {
        $data = [
            'labels' => ['LinkedIn', 'Indeed', 'Site Web', 'Recommandation', 'Agence', 'Autre'],
            'data' => [85, 62, 45, 28, 15, 10],
        ];

        $chartUrl = $this->chartService->createPieChart(
            $data['labels'],
            $data['data'],
            ['title' => 'Sources de Candidatures']
        );

        return new JsonResponse(['chart_url' => $chartUrl, 'data' => $data]);
    }

    #[Route('/api/hiring-trend', name: 'charts_api_hiring', methods: ['GET'])]
    public function hiringTrend(Request $request): JsonResponse
    {
        $months = (int) $request->query->get('months', 6);
        
        $labels = [];
        $applications = [];
        $hires = [];
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $labels[] = date('M Y', strtotime("-$i months"));
            $applications[] = rand(40, 80);
            $hires[] = rand(3, 8);
        }

        $chartUrl = $this->chartService->createLineChart(
            $labels,
            [
                ['label' => 'Candidatures', 'data' => $applications, 'backgroundColor' => 'rgba(79, 70, 229, 0.3)'],
                ['label' => 'Embauches', 'data' => $hires, 'backgroundColor' => 'rgba(16, 185, 129, 0.3)'],
            ],
            ['title' => 'Tendances Recrutement', 'tension' => 0.4]
        );

        return new JsonResponse([
            'chart_url' => $chartUrl,
            'data' => ['labels' => $labels, 'applications' => $applications, 'hires' => $hires]
        ]);
    }

    #[Route('/api/funnel-stats', name: 'charts_api_funnel', methods: ['GET'])]
    public function funnelStats(): JsonResponse
    {
        $stages = ['Candidatures', 'Présélection', 'Entretiens', 'Offres', 'Embauches'];
        $counts = [320, 180, 75, 35, 28];

        $chartUrl = $this->chartService->createHorizontalBarChart(
            $stages,
            [['label' => 'Candidats', 'data' => $counts, 'backgroundColor' => '#4f46e5']],
            ['title' => 'Entonnoir de Recrutement', 'beginAtZero' => true]
        );

        return new JsonResponse(['chart_url' => $chartUrl, 'stages' => $stages, 'counts' => $counts]);
    }

    #[Route('/api/job-offer-performance', name: 'charts_api_jobs', methods: ['GET'])]
    public function jobOfferPerformance(): JsonResponse
    {
        $jobs = ['Dev Senior', 'Marketing', 'RH', 'Commercial', 'Designer'];
        $views = [450, 280, 150, 320, 200];
        $applications = [45, 32, 18, 38, 22];

        $chartUrl = $this->chartService->createBarChart(
            $jobs,
            [
                ['label' => 'Vues', 'data' => $views, 'backgroundColor' => '#4f46e5'],
                ['label' => 'Candidatures', 'data' => $applications, 'backgroundColor' => '#10b981'],
            ],
            ['title' => 'Performance des Offres']
        );

        return new JsonResponse([
            'chart_url' => $chartUrl,
            'jobs' => $jobs,
            'views' => $views,
            'applications' => $applications
        ]);
    }

    #[Route('/api/application-status', name: 'charts_api_status', methods: ['GET'])]
    public function applicationStatus(): JsonResponse
    {
        $statuses = ['Nouveau', 'En Cours', 'Entretien', 'Offre', 'Embauché', 'Refusé'];
        $counts = [50, 80, 35, 20, 15, 40];

        $chartUrl = $this->chartService->createDoughnutChart(
            $statuses,
            $counts,
            ['title' => 'Statut des Candidatures']
        );

        return new JsonResponse(['chart_url' => $chartUrl, 'statuses' => $statuses, 'counts' => $counts]);
    }

    #[Route('/api/custom', name: 'charts_api_custom', methods: ['POST'])]
    public function customChart(Request $request): JsonResponse
    {
        $config = json_decode($request->getContent(), true);

        if (!$this->chartService->validateConfig($config)) {
            return new JsonResponse(['error' => 'Invalid chart configuration'], 400);
        }

        try {
            $url = $this->chartService->getChartUrl($config, $config['options'] ?? []);
            return new JsonResponse(['url' => $url]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/export/{chartId}', name: 'charts_export', methods: ['GET'])]
    public function exportChart(string $chartId, Request $request): Response
    {
        $format = $request->query->get('format', 'png');
        
        // In a real implementation, you would retrieve the chart config by ID
        // and generate the image using getChartImage()
        
        // For now, return a placeholder response
        return new Response('Export functionality - Chart ID: ' . $chartId, 200);
    }
}
