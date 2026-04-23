<?php

namespace App\Service\AI\Tool\Candidate;

use App\Service\AI\Tool\ToolInterface;
use App\Repository\Recrutement\CandidateRepository;

class GetCandidatesTool implements ToolInterface
{
    public function __construct(
        private CandidateRepository $candidateRepository
    ) {}

    public function getName(): string
    {
        return 'get_candidates';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'get_candidates',
            'description' => 'Lists all registered candidates in the system.',
            'parameters' => [
                'type' => 'object',
                'properties' => (object)[
                    'search' => [
                        'type' => 'string',
                        'description' => 'Search by name or email'
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
        $limit = $args['limit'] ?? 10;

        $candidates = $this->candidateRepository->findAll();
        
        $result = [];
        foreach ($candidates as $cand) {
            if (isset($args['search']) && !empty($args['search'])) {
                $search = strtolower($args['search']);
                $nameMatch = $cand->getFullName() && str_contains(strtolower($cand->getFullName()), $search);
                $emailMatch = $cand->getEmail() && str_contains(strtolower($cand->getEmail()), $search);
                if (!$nameMatch && !$emailMatch) {
                    continue;
                }
            }

            $result[] = [
                'id' => $cand->getId(),
                'username' => $cand->getUsername(),
                'full_name' => $cand->getFullName(),
                'email' => $cand->getEmail(),
                'phone' => $cand->getPhone(),
                'created_at' => $cand->getCreatedAt()?->format('Y-m-d H:i')
            ];

            if (count($result) >= $limit) {
                break;
            }
        }

        return [
            'candidates' => $result,
            'total' => count($result)
        ];
    }
}