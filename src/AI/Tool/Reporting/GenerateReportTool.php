<?php

declare(strict_types=1);

namespace App\AI\Tool\Reporting;

use App\AI\Tool\ApplicationTool;
use Doctrine\ORM\EntityManagerInterface;

final class GenerateReportTool extends ApplicationTool
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    protected function getToolName(): string
    {
        return 'generate_report';
    }

    protected function getToolDescription(): string
    {
        return 'Génère un rapport statistiques sur les candidatures.';
    }

    protected function getParameters(): array
    {
        return [
            'type' => ['type' => 'string', 'description' => 'Type de rapport (pipeline, performance)'],
            'job_offer_id' => ['type' => 'integer', 'description' => 'ID de l\'offre (optionnel)'],
            'from_date' => ['type' => 'string', 'description' => 'Date de début'],
            'to_date' => ['type' => 'string', 'description' => 'Date de fin'],
        ];
    }

    protected function getRequired(): array
    {
        return ['type'];
    }

    public function execute(array $args, object $user): \App\AI\Domain\ValueObject\ToolOutput
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('a.status', 'COUNT(a.id) as cnt')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->where('a.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->groupBy('a.status');

        if (isset($args['job_offer_id'])) {
            $qb->andWhere('a.jobOffer = :jobId')
                ->setParameter('jobId', $args['job_offer_id']);
        }

        $results = $qb->getQuery()->getResult();

        $stats = [];
        $total = 0;
        foreach ($results as $row) {
            $status = $row['status'];
            $count = (int) $row['cnt'];
            $total += $count;
            $stats[$status] = $count;
        }

        $pipelineData = [
            'PENDING' => $stats['PENDING'] ?? 0,
            'REVIEWING' => $stats['REVIEWING'] ?? 0,
            'INTERVIEW' => $stats['INTERVIEW'] ?? 0,
            'OFFER' => $stats['OFFER'] ?? 0,
            'HIRED' => $stats['HIRED'] ?? 0,
            'REJECTED' => $stats['REJECTED'] ?? 0,
        ];

        $summary = sprintf(
            "Rapport généré: %d candidature(s) analysée(s).\nRépartition par statut:\n" .
            "- PENDING: %d\n- REVIEWING: %d\n- INTERVIEW: %d\n- OFFER: %d\n- HIRED: %d\n- REJECTED: %d",
            $total,
            $pipelineData['PENDING'],
            $pipelineData['REVIEWING'],
            $pipelineData['INTERVIEW'],
            $pipelineData['OFFER'],
            $pipelineData['HIRED'],
            $pipelineData['REJECTED'],
        );

        return $this->createOutput($summary, [
            'type' => 'pipeline_report',
            'pipeline' => $pipelineData,
            'total' => $total,
        ]);
    }
}