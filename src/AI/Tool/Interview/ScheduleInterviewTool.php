<?php

declare(strict_types=1);

namespace App\AI\Tool\Interview;

use App\AI\Tool\InterviewTool;
use Doctrine\ORM\EntityManagerInterface;

final class ScheduleInterviewTool extends InterviewTool
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    protected function getToolName(): string
    {
        return 'schedule_interview';
    }

    protected function getToolDescription(): string
    {
        return 'Planifie un entretien avec un candidat.';
    }

    protected function getParameters(): array
    {
        return [
            'application_id' => ['type' => 'integer', 'description' => 'ID de la candidature'],
            'type' => ['type' => 'string', 'description' => 'Type d\'entretien (téléphonique, visio, présentiel)'],
            'date' => ['type' => 'string', 'description' => 'Date et heure (Y-m-d H:i)'],
            'duration' => ['type' => 'integer', 'description' => 'Durée en minutes (défaut: 60)'],
            'notes' => ['type' => 'string', 'description' => 'Notes optionnelles'],
        ];
    }

    protected function getRequired(): array
    {
        return ['application_id', 'type', 'date'];
    }

    public function execute(array $args, object $user): \App\AI\Domain\ValueObject\ToolOutput
    {
        $application = $this->em->find(\App\Entity\Recrutement\Application::class, $args['application_id']);

        if ($application === null) {
            return $this->createOutput("Candidature non trouvée: {$args['application_id']}");
        }

        $type = $args['type'];
        $date = $args['date'];

        $summary = "Entretien {$type} planifié pour le {$date} avec {$application->getCandidateName()}. Confirmation requise.";

        return $this->createOutput($summary, [
            'type' => 'interview_scheduled',
            'application_id' => $args['application_id'],
            'candidate_name' => $application->getCandidateName(),
            'type' => $type,
            'date' => $date,
            'duration' => $args['duration'] ?? 60,
        ], true);
    }
}