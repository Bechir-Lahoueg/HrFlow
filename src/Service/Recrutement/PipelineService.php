<?php

declare(strict_types=1);

namespace App\Service\Recrutement;

use App\AI\Domain\DTO\PipelineQuery;
use App\AI\Domain\DTO\PipelineResult;
use Doctrine\ORM\EntityManagerInterface;

final class PipelineService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function execute(PipelineQuery $query): PipelineResult
    {
        $summary = $this->getSummary($query);
        $byStage = $this->getByStage($query);
        $byDepartment = $this->getByDepartment($query);
        $byOffer = $this->getByOffer($query);
        $overTime = $this->getOverTime($query);

        $visualizationHints = ['bar', 'funnel'];
        if ($query->groupBy === 'department') {
            $visualizationHints = ['bar', 'pie'];
        } elseif ($query->groupBy === 'time') {
            $visualizationHints = ['line'];
        }

        return new PipelineResult(
            summary: $summary,
            byStage: $byStage,
            byDepartment: $byDepartment,
            byOffer: $byOffer,
            overTime: $overTime,
            visualizationHints: $visualizationHints,
        );
    }

    private function getSummary(PipelineQuery $query): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(a.id) as total', 'a.status')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->where('a.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->groupBy('a.status');

        $this->applyFilters($qb, $query);

        $results = $qb->getQuery()->getResult();

        $total = 0;
        $hired = 0;
        foreach ($results as $row) {
            $total += (int) $row['total'];
            if ($row['status'] === 'HIRED') {
                $hired = (int) $row['total'];
            }
        }

        return [
            'total_applications' => $total,
            'conversion_rate' => $total > 0 ? round($hired / $total, 2) : 0,
        ];
    }

    private function getByStage(PipelineQuery $query): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('a.status', 'COUNT(a.id) as cnt')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->where('a.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->groupBy('a.status');

        $this->applyFilters($qb, $query);

        $results = $qb->getQuery()->getResult();

        $statusLabels = [
            'PENDING' => 'En attente',
            'REVIEWING' => 'En révision',
            'INTERVIEW' => 'Entretien',
            'OFFER' => 'Offre',
            'HIRED' => 'Recruté',
            'REJECTED' => 'Refusé',
        ];

        $byStage = [];
        foreach ($results as $row) {
            $byStage[$statusLabels[$row['status']] ?? $row['status']] = (int) $row['cnt'];
        }

        return $byStage;
    }

    private function getByDepartment(PipelineQuery $query): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('a.department', 'COUNT(a.id) as cnt')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->where('a.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->groupBy('a.department');

        $this->applyFilters($qb, $query);

        $results = $qb->getQuery()->getResult();

        $byDepartment = [];
        foreach ($results as $row) {
            $dept = $row['department'] ?? 'Non défini';
            $byDepartment[$dept] = round((int) $row['cnt'] / max(1, array_sum(array_column($results, 'cnt')), 0), 2);
        }

        return $byDepartment;
    }

    private function getByOffer(PipelineQuery $query): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('j.title', 'COUNT(a.id) as cnt')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->join('a.jobOffer', 'j')
            ->where('a.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->groupBy('j.title')
            ->orderBy('cnt', 'DESC');

        $this->applyFilters($qb, $query);

        $results = $qb->getQuery()->getResult();

        $byOffer = [];
        foreach ($results as $row) {
            $byOffer[$row['title']] = (int) $row['cnt'];
        }

        return $byOffer;
    }

    private function getOverTime(PipelineQuery $query): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select("DATE_FORMAT(a.appliedAt, '%Y-%m-%d') as date", 'COUNT(a.id) as cnt')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->where('a.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->groupBy('date')
            ->orderBy('date', 'ASC');

        $this->applyFilters($qb, $query);

        $results = $qb->getQuery()->getResult();

        $overTime = [];
        foreach ($results as $row) {
            $overTime[$row['date']] = (int) $row['cnt'];
        }

        return $overTime;
    }

    private function applyFilters($qb, PipelineQuery $query): void
    {
        if ($query->department !== null) {
            $qb->andWhere('a.department = :dept')
                ->setParameter('dept', $query->department);
        }

        if ($query->status !== null) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $query->status);
        }

        if ($query->jobOfferId !== null) {
            $qb->andWhere('a.jobOffer = :jobId')
                ->setParameter('jobId', $query->jobOfferId);
        }

        if ($query->dateRange !== null) {
            $this->applyDateRange($qb, $query->dateRange);
        }
    }

    private function applyDateRange($qb, string $dateRange): void
    {
        $now = new \DateTime();
        $from = match ($dateRange) {
            '1_month', '1_month' => (clone $now)->modify('-1 month'),
            '3_months' => (clone $now)->modify('-3 months'),
            '6_months' => (clone $now)->modify('-6 months'),
            '1_year' => (clone $now)->modify('-1 year'),
            default => null,
        };

        if ($from !== null) {
            $qb->andWhere('a.appliedAt >= :from')
                ->setParameter('from', $from);
        }
    }
}
