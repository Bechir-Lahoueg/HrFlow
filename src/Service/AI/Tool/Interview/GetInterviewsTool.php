<?php

namespace App\Service\AI\Tool\Interview;

use App\Service\AI\Tool\ToolInterface;
use App\Repository\Recrutement\InterviewRepository;
use Symfony\Bundle\SecurityBundle\Security;

class GetInterviewsTool implements ToolInterface
{
    public function __construct(
        private InterviewRepository $interviewRepository,
        private Security $security
    ) {}

    public function getName(): string
    {
        return 'get_interviews';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'get_interviews',
            'description' => 'Lists interviews with optional filters for status, result, or date range.',
            'parameters' => [
                'type' => 'object',
                'properties' => (object)[
                    'result' => [
                        'type' => 'string',
                        'enum' => ['PENDING', 'PASSED', 'FAILED', 'NO_SHOW'],
                        'description' => 'Filter by interview result'
                    ],
                    'application_id' => [
                        'type' => 'integer',
                        'description' => 'Filter by application ID'
                    ],
                    'upcoming' => [
                        'type' => 'boolean',
                        'description' => 'Only show upcoming interviews',
                        'default' => false
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Number of interviews to return',
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

        $interviews = $this->interviewRepository->findByRh(
            $user,
            $args['application_id'] ?? null
        );

        $limit = $args['limit'] ?? 10;
        $interviews = array_slice($interviews, 0, $limit);

        $now = new \DateTime();
        $result = [];
        foreach ($interviews as $interview) {
            $interviewDate = $interview->getInterviewDate();
            $isUpcoming = $interviewDate && $interviewDate > $now;

            if (isset($args['upcoming']) && $args['upcoming'] && !$isUpcoming) {
                continue;
            }

            if (isset($args['result']) && $interview->getResult() !== $args['result']) {
                continue;
            }

            $result[] = [
                'id' => $interview->getId(),
                'candidate_name' => $interview->getApplication()?->getCandidateName(),
                'application_id' => $interview->getApplication()?->getId(),
                'job_title' => $interview->getApplication()?->getJobOffer()?->getTitle(),
                'type' => $interview->getType(),
                'interview_date' => $interviewDate?->format('Y-m-d H:i'),
                'location' => $interview->getLocation(),
                'meeting_link' => $interview->getMeetingLink(),
                'result' => $interview->getResult(),
                'score' => $interview->getScore(),
                'is_upcoming' => $isUpcoming
            ];
        }

        return [
            'interviews' => $result,
            'total' => count($result)
        ];
    }
}