<?php

declare(strict_types=1);

namespace App\AI\Tool\Reporting;

use App\AI\Contract\ToolInterface;
use App\AI\Domain\ValueObject\ToolOutput;
use Doctrine\ORM\EntityManagerInterface;

final class GenerateChartTool implements ToolInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function getName(): string
    {
        return 'generate_chart';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'generate_chart',
            'description' => 'Génère un graphique (barres, lignes, camembert) à partir des données de recrutement.',
            'parameters' => [
                'chart_type' => [
                    'type' => 'string',
                    'description' => 'Type de graphique: bar, line, pie, doughnut, radar',
                ],
                'data_type' => [
                    'type' => 'string',
                    'description' => 'Source des données: pipeline, applications_by_offer, applications_over_time, interview_results',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Titre du graphique',
                ],
                'job_offer_id' => ['type' => 'integer', 'description' => 'ID de l\'offre (filtre optionnel)'],
                'from_date' => ['type' => 'string', 'description' => 'Date de début (YYYY-MM-DD)'],
                'to_date' => ['type' => 'string', 'description' => 'Date de fin (YYYY-MM-DD)'],
            ],
            'required' => ['chart_type', 'data_type'],
        ];
    }

    public function execute(array $args, object $user): ToolOutput
    {
        $chartType = $args['chart_type'] ?? 'bar';
        $dataType = $args['data_type'] ?? 'pipeline';
        $title = $args['title'] ?? null;

        $validChartTypes = ['bar', 'line', 'pie', 'doughnut', 'radar'];
        if (!in_array($chartType, $validChartTypes)) {
            return new ToolOutput(
                llmSummary: "Type de graphique invalide: {$chartType}. Types possibles: " . implode(', ', $validChartTypes),
            );
        }

        $validDataTypes = ['pipeline', 'applications_by_offer', 'applications_over_time', 'interview_results', 'hiring_funnel'];
        if (!in_array($dataType, $validDataTypes)) {
            return new ToolOutput(
                llmSummary: "Type de données invalide: {$dataType}. Types possibles: " . implode(', ', $validDataTypes),
            );
        }

        $chartData = match ($dataType) {
            'pipeline' => $this->getPipelineData($args),
            'applications_by_offer' => $this->getApplicationsByOffer($args),
            'applications_over_time' => $this->getApplicationsOverTime($args),
            'interview_results' => $this->getInterviewResults($args),
            'hiring_funnel' => $this->getHiringFunnel($args),
            default => [],
        };

        $chartConfig = $this->buildChartConfig($chartType, $chartData, $title);

        $summary = sprintf(
            "Graphique %s généré: %s (%d points de données).",
            $chartType,
            $chartData['label'] ?? $dataType,
            count($chartData['labels'] ?? []),
        );

        return new ToolOutput(
            llmSummary: $summary,
            uiPayload: [
                'type' => 'chart',
                'chart_config' => $chartConfig,
                'chart_data' => $chartData,
            ],
        );
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function getPipelineData(array $args): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('a.status', 'COUNT(a.id) as cnt')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->where('a.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->groupBy('a.status');

        $results = $qb->getQuery()->getResult();
        $labels = [];
        $data = [];
        $colors = ['#f59e0b', '#3b82f6', '#8b5cf6', '#10b981', '#059669', '#ef4444'];
        $statusLabels = [
            'PENDING' => 'En attente',
            'REVIEWING' => 'En révision',
            'INTERVIEW' => 'Entretien',
            'OFFER' => 'Offre',
            'HIRED' => 'Recruté',
            'REJECTED' => 'Refusé',
        ];

        $statusOrder = ['PENDING', 'REVIEWING', 'INTERVIEW', 'OFFER', 'HIRED', 'REJECTED'];
        $stats = [];
        foreach ($results as $row) {
            $stats[$row['status']] = (int) $row['cnt'];
        }

        foreach ($statusOrder as $status) {
            if (isset($stats[$status])) {
                $labels[] = $statusLabels[$status];
                $data[] = $stats[$status];
            }
        }

        return [
            'label' => 'Pipeline de recrutement',
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Candidatures',
                'data' => $data,
                'backgroundColor' => array_slice($colors, 0, count($labels)),
                'borderRadius' => 6,
            ]],
        ];
    }

    /** @param array<mixed> $args @return array<mixed> */
    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function getApplicationsByOffer(array $args): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('j.title', 'COUNT(a.id) as cnt')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->join('a.jobOffer', 'j')
            ->where('a.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->groupBy('j.title')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults(10);

        $results = $qb->getQuery()->getResult();
        $labels = [];
        $data = [];
        $colors = ['#14b8a6', '#7c3aed', '#f59e0b', '#ef4444', '#3b82f6', '#10b981', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];

        foreach ($results as $row) {
            $labels[] = $row['title'];
            $data[] = (int) $row['cnt'];
        }

        return [
            'label' => 'Candidatures par offre',
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Candidatures',
                'data' => $data,
                'backgroundColor' => array_slice($colors, 0, count($labels)),
                'borderRadius' => 6,
            ]],
        ];
    }

    /** @param array<mixed> $args @return array<mixed> */
    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function getApplicationsOverTime(array $args): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select("DATE_FORMAT(a.appliedAt, '%Y-%m-%d') as date", 'COUNT(a.id) as cnt')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->where('a.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->groupBy('date')
            ->orderBy('date', 'ASC');

        $results = $qb->getQuery()->getResult();
        $labels = [];
        $data = [];

        foreach ($results as $row) {
            $labels[] = $row['date'];
            $data[] = (int) $row['cnt'];
        }

        return [
            'label' => 'Candidatures dans le temps',
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Candidatures',
                'data' => $data,
                'backgroundColor' => 'rgba(20, 184, 166, 0.2)',
                'borderColor' => '#14b8a6',
                'fill' => true,
                'tension' => 0.4,
            ]],
        ];
    }

    /** @param array<mixed> $args @return array<mixed> */
    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function getInterviewResults(array $args): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('i.result', 'COUNT(i.id) as cnt')
            ->from(\App\Entity\Recrutement\Interview::class, 'i')
            ->where('i.result IS NOT NULL')
            ->groupBy('i.result');

        $results = $qb->getQuery()->getResult();
        $labels = [];
        $data = [];
        $colors = ['#10b981', '#ef4444', '#f59e0b'];

        foreach ($results as $row) {
            $labels[] = $row['result'] ?? 'N/A';
            $data[] = (int) $row['cnt'];
        }

        return [
            'label' => 'Résultats des entretiens',
            'labels' => $labels,
            'datasets' => [[
                'data' => $data,
                'backgroundColor' => array_slice($colors, 0, count($labels)),
            ]],
        ];
    }

    /** @param array<mixed> $args @return array<mixed> */
    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function getHiringFunnel(array $args): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('a.status', 'COUNT(a.id) as cnt')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->where('a.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->groupBy('a.status');

        $results = $qb->getQuery()->getResult();
        $stats = [];
        foreach ($results as $row) {
            $stats[$row['status']] = (int) $row['cnt'];
        }

        $labels = ['Postulé', 'En révision', 'Entretien', 'Offre', 'Recruté'];
        $data = [
            ($stats['PENDING'] ?? 0) + ($stats['REVIEWING'] ?? 0),
            $stats['REVIEWING'] ?? 0,
            $stats['INTERVIEW'] ?? 0,
            $stats['OFFER'] ?? 0,
            $stats['HIRED'] ?? 0,
        ];
        $colors = ['#94a3b8', '#3b82f6', '#8b5cf6', '#10b981', '#059669'];

        return [
            'label' => 'Entonnoir de recrutement',
            'labels' => $labels,
            'datasets' => [[
                'data' => $data,
                'backgroundColor' => $colors,
            ]],
        ];
    }

    /** @param array<mixed> $chartData @return array<mixed> */
    /**
     * @param array<string, mixed> $chartData
     * @return array<string, mixed>
     */
    private function buildChartConfig(string $chartType, array $chartData, ?string $title): array
    {
        return [
            'type' => $chartType,
            'title' => $title ?? $chartData['label'] ?? '',
            'data' => $chartData,
        ];
    }
}
