<?php

namespace App\Service\AI\Tool\Candidate;

use App\Service\AI\Tool\ToolInterface;
use App\Repository\Recrutement\ApplicationRepository;
use Symfony\Bundle\SecurityBundle\Security;

class GetCandidatesTool implements ToolInterface
{
    public function __construct(
        private ApplicationRepository $applicationRepository,
        private Security $security
    ) {}

    public function getName(): string
    {
        return 'get_candidates';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'get_candidates',
            'description' => 'Lists candidates who have applied to your job offers, with their application status.',
            'parameters' => [
                'type' => 'object',
                'properties' => (object)[
                    'search' => [
                        'type' => 'string',
                        'description' => 'Search by candidate name or email'
                    ],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['PENDING', 'REVIEWING', 'INTERVIEW', 'OFFER', 'HIRED', 'REJECTED'],
                        'description' => 'Filter by application status'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Number of candidates to return',
                        'default' => 10
                    ]
                ],
                'additionalProperties' => false
            ]
        ];
    }

    public function execute(array $args): mixed
    {
        $user = $this->security->getUser();
        if (!$user) return ['error' => 'Not authenticated'];

        $limit = $args['limit'] ?? 10;
        $status = $args['status'] ?? null;

        // Get applications for this RH's job offers (not all candidates)
        $applications = $this->applicationRepository->findByRh(
            $user,
            null,  // job_id
            $status,
            null,  // dateFrom
            $args['search'] ?? null
        );

        // Extract unique candidates with their latest application status
        $candidates = [];
        $seenCandidateIds = [];

        foreach ($applications as $app) {
            $candidate = $app->getCandidate();
            if (!$candidate) continue;

            $candidateId = $candidate->getId();

            // Skip if we've already seen this candidate
            if (in_array($candidateId, $seenCandidateIds)) continue;
            $seenCandidateIds[] = $candidateId;

            $candidates[] = [
                'id' => $candidateId,
                'full_name' => $app->getCandidateName(),
                'email' => $app->getEmailAddress(),
                'phone' => $candidate->getPhone(),
                'status' => $app->getStatus(),
                'status_label' => $app->getStatusLabel(),
                'job_title' => $app->getJobOffer()?->getTitle(),
                'job_id' => $app->getJobOffer()?->getId(),
                'applied_at' => $app->getAppliedAt()?->format('Y-m-d H:i')
            ];

            if (count($candidates) >= $limit) {
                break;
            }
        }

        return [
            'candidates' => $candidates,
            'total' => count($candidates)
        ];
    }
}