<?php

namespace App\Service\AI\Tool\Interview;

use App\Service\AI\Tool\ToolInterface;
use App\Service\Recrutement\InterviewConflictDetector;
use Symfony\Bundle\SecurityBundle\Security;

class GetAvailableSlotsTool implements ToolInterface
{
    public function __construct(
        private InterviewConflictDetector $conflictDetector,
        private Security $security
    ) {}

    public function getName(): string
    {
        return 'get_available_slots';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'get_available_slots',
            'description' => 'Finds available interview time slots for an interviewer on a specific date.',
            'parameters' => [
                'type' => 'object',
                'properties' => (object)[
                    'date' => [
                        'type' => 'string',
                        'format' => 'date',
                        'description' => 'The date to check (YYYY-MM-DD)'
                    ],
                    'interviewer_id' => [
                        'type' => 'integer',
                        'description' => 'Optional ID of the interviewer. Defaults to the current user.'
                    ]
                ],
                'required' => ['date'],
                'additionalProperties' => false
            ]
        ];
    }

    public function execute(array $args): mixed
    {
        $dateStr = $args['date'];
        $date = new \DateTime($dateStr);
        
        $interviewerId = $args['interviewer_id'] ?? null;
        if (!$interviewerId) {
            $user = $this->security->getUser();
            // Assuming User entity has getId() and can be an interviewer
            if (method_exists($user, 'getId')) {
                $interviewerId = $user->getId();
            }
        }

        if (!$interviewerId) {
            return ['error' => 'Interviewer ID is required but could not be determined.'];
        }

        $slots = $this->conflictDetector->getAvailableTimeSlots($interviewerId, $date);
        
        return [
            'date' => $dateStr,
            'interviewer_id' => $interviewerId,
            'available_slots' => array_filter($slots, fn($s) => $s['available'])
        ];
    }
}
