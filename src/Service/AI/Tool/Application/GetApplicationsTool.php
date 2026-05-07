<?php

namespace App\Service\AI\Tool\Application;

use App\Service\AI\Tool\ToolInterface;
use App\Repository\Recrutement\ApplicationRepository;
use Symfony\Bundle\SecurityBundle\Security;

class GetApplicationsTool implements ToolInterface
{
    public function __construct(
        private ApplicationRepository $applicationRepository,
        private Security $security
    ) {}

    public function getName(): string
    {
        return 'get_applications';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'get_applications',
            'description' => 'Lists job applications with optional filters for status, job, or candidate name.',
            'parameters' => [
                'type' => 'object',
                'properties' => (object)[
                    'status' => [
                        'type' => 'string',
                        'enum' => ['PENDING', 'REVIEWING', 'INTERVIEW', 'OFFER', 'HIRED', 'REJECTED'],
                        'description' => 'Filter by application status'
                    ],
                    'job_id' => [
                        'type' => 'integer',
                        'description' => 'Filter by job offer ID'
                    ],
                    'candidate_search' => [
                        'type' => 'string',
                        'description' => 'Search by candidate name'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Number of applications to return',
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

        $applications = $this->applicationRepository->findByRh(
            $user,
            $args['job_id'] ?? null,
            $args['status'] ?? null,
            null,
            $args['candidate_search'] ?? null
        );

        $limit = $args['limit'] ?? 10;
        $applications = array_slice($applications, 0, $limit);

        $result = [];
        foreach ($applications as $app) {
            $result[] = [
                'id' => $app->getId(),
                'candidate_name' => $app->getCandidateName(),
                'email' => $app->getEmailAddress(),
                'job_title' => $app->getJobOffer()?->getTitle(),
                'job_id' => $app->getJobOffer()?->getId(),
                'status' => $app->getStatus(),
                'status_label' => $app->getStatusLabel(),
                'department' => $app->getDepartment(),
                'applied_at' => $app->getAppliedAt()?->format('Y-m-d H:i'),
                'experience_level' => $app->getExperienceLevel()
            ];
        }

        return [
            'applications' => $result,
            'total' => count($result)
        ];
    }
}