<?php

declare(strict_types=1);

namespace App\Service\Recrutement;

use Doctrine\ORM\EntityManagerInterface;

final class ReportService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PipelineService $pipelineService,
        private readonly CandidateService $candidateService,
    ) {}

    public function generate(string $type, array $options = []): array
    {
        return match ($type) {
            'pipeline' => $this->generatePipelineReport($options),
            'candidates' => $this->generateCandidatesReport($options),
            'interviews' => $this->generateInterviewsReport($options),
            'hiring_funnel' => $this->generateHiringFunnelReport($options),
            default => ['error' => 'Unknown report type'],
        };
    }

    private function generatePipelineReport(array $options): array
    {
        $query = new \App\AI\Domain\DTO\PipelineQuery(
            department: $options['department'] ?? null,
            dateRange: $options['date_range'] ?? null,
        );

        $result = $this->pipelineService->execute($query);

        return [
            'type' => 'pipeline',
            'summary' => $result->summary,
            'by_stage' => $result->byStage,
            'by_department' => $result->byDepartment,
            'visualization_hints' => $result->visualizationHints,
        ];
    }

    private function generateCandidatesReport(array $options): array
    {
        $query = new \App\AI\Domain\DTO\CandidateQuery(
            jobOfferId: $options['job_offer_id'] ?? null,
            status: $options['status'] ?? null,
            limit: $options['limit'] ?? 100,
        );

        $result = $this->candidateService->search($query);

        return [
            'type' => 'candidates',
            'total' => $result->total,
            'candidates' => $result->candidates,
            'visualization_hints' => $result->visualizationHints,
        ];
    }

    private function generateInterviewsReport(array $options): array
    {
        $query = new \App\AI\Domain\DTO\InterviewQuery(
            dateFrom: $options['date_from'] ?? null,
            dateTo: $options['date_to'] ?? null,
        );

        $interviewService = new InterviewService($this->em);
        $interviews = $interviewService->list($query);
        $stats = $interviewService->getStats();

        return [
            'type' => 'interviews',
            'total' => count($interviews),
            'interviews' => $interviews,
            'stats' => $stats,
            'visualization_hints' => ['table', 'pie'],
        ];
    }

    private function generateHiringFunnelReport(array $options): array
    {
        $query = new \App\AI\Domain\DTO\PipelineQuery();
        $result = $this->pipelineService->execute($query);

        $funnel = [
            'Postulé' => ($result->byStage['En attente'] ?? 0) + ($result->byStage['En révision'] ?? 0),
            'En révision' => $result->byStage['En révision'] ?? 0,
            'Entretien' => $result->byStage['Entretien'] ?? 0,
            'Offre' => $result->byStage['Offre'] ?? 0,
            'Recruté' => $result->byStage['Recruté'] ?? 0,
        ];

        return [
            'type' => 'hiring_funnel',
            'funnel' => $funnel,
            'visualization_hints' => ['funnel', 'bar'],
        ];
    }
}
