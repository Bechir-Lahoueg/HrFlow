<?php

namespace App\Service\AI\Tool\Interview;

use App\Service\AI\Tool\ToolInterface;
use App\Entity\Recrutement\Interview;
use App\Repository\Recrutement\ApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class ScheduleTool implements ToolInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ApplicationRepository $applicationRepository,
        private Security $security
    ) {}

    public function getName(): string
    {
        return 'schedule_interview';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'schedule_interview',
            'description' => 'Schedules a new interview for a candidate application.',
            'parameters' => [
                'type' => 'object',
                'properties' => (object)[
                    'application_id' => [
                        'type' => 'integer',
                        'description' => 'The ID of the candidate application'
                    ],
                    'date' => [
                        'type' => 'string',
                        'format' => 'date-time',
                        'description' => 'The interview date and time (YYYY-MM-DD HH:MM)'
                    ],
                    'type' => [
                        'type' => 'string',
                        'enum' => ['PHONE', 'VIDEO', 'ONSITE', 'TECHNICAL', 'HR'],
                        'description' => 'Type of interview'
                    ],
                    'interviewer_id' => [
                        'type' => 'integer',
                        'description' => 'Optional ID of the interviewer. Defaults to the current user.'
                    ],
                    'location' => [
                        'type' => 'string',
                        'description' => 'Physical location or meeting link'
                    ]
                ],
                'required' => ['application_id', 'date', 'type'],
                'additionalProperties' => false
            ]
        ];
    }

    public function execute(array $args): mixed
    {
        $application = $this->applicationRepository->find($args['application_id']);
        if (!$application) {
            return ['error' => "Application with ID {$args['application_id']} not found."];
        }

        $interviewerId = $args['interviewer_id'] ?? null;
        if (!$interviewerId) {
            $user = $this->security->getUser();
            if ($user !== null && method_exists($user, 'getId')) {
                $interviewerId = $user->getId();
            }
        }

        $interview = new Interview();
        $interview->setApplication($application);
        $interview->setInterviewerId($interviewerId);
        $interview->setInterviewDate(new \DateTime($args['date']));
        $interview->setType($args['type']);
        $interview->setResult('PENDING');
        $interview->setIsDeleted(false);

        if (isset($args['location'])) {
            if (filter_var($args['location'], FILTER_VALIDATE_URL)) {
                $interview->setMeetingLink($args['location']);
            } else {
                $interview->setLocation($args['location']);
            }
        }

        $this->entityManager->persist($interview);
        $this->entityManager->flush();

        return [
            'status' => 'success',
            'interview_id' => $interview->getId(),
            'candidate' => $application->getCandidateName(),
            'date' => $interview->getInterviewDate()?->format('Y-m-d H:i') ?? '',
            'type' => $interview->getType()
        ];
    }
}
