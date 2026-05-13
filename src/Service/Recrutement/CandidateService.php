<?php

declare(strict_types=1);

namespace App\Service\Recrutement;

use App\AI\Domain\DTO\CandidateQuery;
use App\AI\Domain\DTO\CandidateResult;
use Doctrine\ORM\EntityManagerInterface;

final class CandidateService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function search(CandidateQuery $query): CandidateResult
    {
        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->where('a.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->orderBy('a.appliedAt', 'DESC');

        $this->applyFilters($qb, $query);

        if ($query->limit !== null) {
            $qb->setMaxResults($query->limit);
        }

        $applications = $qb->getQuery()->getResult();

        $candidates = [];
        foreach ($applications as $app) {
            $candidates[] = [
                'id' => $app->getId(),
                'candidate_name' => $app->getCandidateName(),
                'email' => $app->getEmailAddress(),
                'job_title' => $app->getJobOffer()?->getTitle(),
                'status' => $app->getStatus(),
                'status_label' => $app->getStatusLabel(),
                'applied_at' => $app->getAppliedAt()?->format('Y-m-d H:i:s'),
                'department' => $app->getDepartment(),
                'experience_level' => $app->getExperienceLevel(),
            ];
        }

        return new CandidateResult(
            candidates: $candidates,
            total: count($candidates),
            visualizationHints: ['table'],
        );
    }

    public function rank(CandidateQuery $query): CandidateResult
    {
        $result = $this->search($query);

        $ranked = $result->candidates;
        usort($ranked, function ($a, $b) {
            $scoreA = $this->calculateScore($a);
            $scoreB = $this->calculateScore($b);
            return $scoreB <=> $scoreA;
        });

        $ranked = array_values($ranked);

        return new CandidateResult(
            candidates: $ranked,
            total: count($ranked),
            ranking: array_map(fn($c, $i) => ['rank' => $i + 1] + $c, $ranked, array_keys($ranked)),
            visualizationHints: ['table'],
        );
    }

    public function compare(array $ids): CandidateResult
    {
        if (empty($ids)) {
            return new CandidateResult();
        }

        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->where('a.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', $ids);

        $applications = $qb->getQuery()->getResult();

        $candidates = [];
        foreach ($applications as $app) {
            $candidates[] = [
                'id' => $app->getId(),
                'candidate_name' => $app->getCandidateName(),
                'email' => $app->getEmailAddress(),
                'job_title' => $app->getJobOffer()?->getTitle(),
                'status' => $app->getStatus(),
                'department' => $app->getDepartment(),
                'experience_level' => $app->getExperienceLevel(),
                'applied_at' => $app->getAppliedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return new CandidateResult(
            candidates: $candidates,
            total: count($candidates),
            comparison: $candidates,
            visualizationHints: ['table'],
        );
    }

    private function calculateScore(array $candidate): float
    {
        $score = 0.0;

        $experience = $candidate['experience_level'] ?? '';
        $score += match ($experience) {
            'Senior' => 3.0,
            'Mid-Level' => 2.0,
            'Junior' => 1.0,
            default => 0.5,
        };

        $status = $candidate['status'] ?? '';
        $score += match ($status) {
            'INTERVIEW' => 2.0,
            'REVIEWING' => 1.0,
            'PENDING' => 0.5,
            default => 0,
        };

        return $score;
    }

    private function applyFilters($qb, CandidateQuery $query): void
    {
        if ($query->jobOfferId !== null) {
            $qb->andWhere('a.jobOffer = :jobId')
                ->setParameter('jobId', $query->jobOfferId);
        }

        if ($query->status !== null) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $query->status);
        }

        if ($query->department !== null) {
            $qb->andWhere('a.department = :dept')
                ->setParameter('dept', $query->department);
        }

        if (!empty($query->ids)) {
            $qb->andWhere('a.id IN (:ids)')
                ->setParameter('ids', $query->ids);
        }
    }
}
