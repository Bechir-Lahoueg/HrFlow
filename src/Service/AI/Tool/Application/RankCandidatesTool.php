<?php

namespace App\Service\AI\Tool\Application;

use App\Service\AI\Tool\ToolInterface;
use App\Repository\Recrutement\ApplicationRepository;
use App\Entity\Recrutement\Application;
use Symfony\Bundle\SecurityBundle\Security;

class RankCandidatesTool implements ToolInterface
{
    public function __construct(
        private ApplicationRepository $applicationRepository,
        private Security $security
    ) {}

    public function getName(): string
    {
        return 'rank_candidates';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'rank_candidates',
            'description' => 'Ranks candidates for a specific job offer based on their interview scores and status.',
            'parameters' => [
                'type' => 'object',
                'properties' => (object)[
                    'job_id' => [
                        'type' => 'integer',
                        'description' => 'The ID of the job offer'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Number of candidates to return',
                        'default' => 5
                    ]
                ],
                'required' => ['job_id'],
                'additionalProperties' => false
            ]
        ];
    }

    public function execute(array $args): mixed
    {
        $jobId = (int)$args['job_id'];
        $limit = $args['limit'] ?? 5;
        $user = $this->security->getUser();

        // Get applications for this job (filtered by RH owner in repository)
        $applications = $this->applicationRepository->findByJobOffer($jobId, $user);

        if (empty($applications)) {
            return ['message' => "No applications found for Job #$jobId."];
        }

        $ranking = [];
        foreach ($applications as $app) {
            $interviews = $app->getInterviews();
            $totalScore = 0;
            $count = 0;

            foreach ($interviews as $interview) {
                if (!$interview->isDeleted()) {
                    $totalScore += $interview->getScore() ?? 0;
                    $count++;
                }
            }

            $avgScore = $count > 0 ? $totalScore / $count : 0;

            $ranking[] = [
                'application_id' => $app->getId(),
                'candidate_name' => $app->getCandidateName(),
                'status' => $app->getStatus(),
                'avg_score' => $avgScore,
                'interview_count' => $count
            ];
        }

        // Sort by avg_score DESC
        usort($ranking, fn($a, $b) => $b['avg_score'] <=> $a['avg_score']);

        return array_slice($ranking, 0, $limit);
    }
}
