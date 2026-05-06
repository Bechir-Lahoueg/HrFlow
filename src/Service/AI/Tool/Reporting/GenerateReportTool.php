<?php

namespace App\Service\AI\Tool\Reporting;

use App\Service\AI\ReportGeneratorService;
use App\Service\AI\Tool\ToolInterface;
use App\Repository\Recrutement\ApplicationRepository;
use App\Repository\Recrutement\JobOfferRepository;
use App\Security\DbUser;
use Symfony\Bundle\SecurityBundle\Security;

class GenerateReportTool implements ToolInterface
{
    public function __construct(
        private ApplicationRepository $applicationRepository,
        private JobOfferRepository $jobOfferRepository,
        private ReportGeneratorService $reportGenerator,
        private Security $security
    ) {}

    public function getName(): string
    {
        return 'generate_report';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'generate_report',
            'description' => 'Generates a recruitment report or chart data. Use format="pdf" for a downloadable document and format="json" for charts.',
            'parameters' => [
                'type' => 'object',
                'properties' => (object)[
                    'report_type' => [
                        'type' => 'string',
                        'enum' => ['pipeline_health', 'job_summary', 'candidate_distribution'],
                        'description' => 'The type of report to generate'
                    ],
                    'format' => [
                        'type' => 'string',
                        'enum' => ['pdf', 'json'],
                        'description' => 'PDF for document, JSON for chart data'
                    ],
                    'job_id' => [
                        'type' => 'integer',
                        'description' => 'Optional filter by Job Offer ID'
                    ],
                    'content' => [
                        'type' => 'string',
                        'description' => 'Custom text or markdown content for the report. If provided, it will be the main body of the PDF.'
                    ]
                ],
                'required' => ['report_type', 'format'],
                'additionalProperties' => false
            ]
        ];
    }

    public function execute(array $args): mixed
    {
        $user = $this->security->getUser();
        if (!$user instanceof DbUser) return ['error' => 'Non authentifié'];

        $jobId = $args['job_id'] ?? null;
        $job = $jobId ? $this->jobOfferRepository->find($jobId) : null;
        
        // Fetch data
        $stats = $this->applicationRepository->getStatusStats($user, $job);
        $total = array_sum($stats);
        
        if ($args['format'] === 'json') {
            return $this->buildChartData($args['report_type'], $stats, $total);
        }

        // PDF Generation
        $data = [
            'title' => $job ? "Poste : " . $job->getTitle() : "Global Pipeline",
            'user_name' => $user->getFullName(),
            'total_applications' => $total,
            'hired_count' => $stats['HIRED'] ?? 0,
            'interview_count' => $stats['INTERVIEW'] ?? 0,
            'status_stats' => $stats,
            'custom_content' => $args['content'] ?? null,
        ];

        if ($job) {
            $recentApps = $this->applicationRepository->findBy(['jobOffer' => $job], ['appliedAt' => 'DESC'], 5);
            $data['recent_applications'] = array_map(fn($a) => [
                'candidate_name' => $a->getCandidateName(),
                'job_title' => $a->getJobOffer()?->getTitle(),
                'applied_at' => $a->getAppliedAt()?->format('d/m/Y'),
                'status' => $a->getStatusLabel(),
            ], $recentApps);
        }

        $url = $this->reportGenerator->generatePdf('ai/reports/pipeline_report.html.twig', $data, 'pipeline_report');

        return [
            'report_type' => $args['report_type'],
            'format' => 'pdf',
            'download_url' => $url,
            'message' => "Le rapport PDF a été généré avec succès."
        ];
    }

    /** @param array<mixed> $stats @return array<mixed> */
    private function buildChartData(string $type, array $stats, int $total): array
    {
        $labels = array_keys($stats);
        $data = array_values($stats);

        return [
            'report_type' => $type,
            'format' => 'json',
            'chart_data' => [
                'type' => ($type === 'pipeline_health') ? 'pie' : 'bar',
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Candidatures',
                    'data' => $data,
                    'backgroundColor' => [
                        '#14b8a6', '#7c3aed', '#f59e0b', '#ef4444', '#3b82f6', '#10b981'
                    ]
                ]]
            ],
            'summary' => "Voici la répartition de vos {$total} candidatures."
        ];
    }
}
