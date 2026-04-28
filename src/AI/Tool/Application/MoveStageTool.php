<?php

declare(strict_types=1);

namespace App\AI\Tool\Application;

use App\AI\Tool\ApplicationTool;
use Doctrine\ORM\EntityManagerInterface;

final class MoveStageTool extends ApplicationTool
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    protected function getToolName(): string
    {
        return 'move_stage';
    }

    protected function getToolDescription(): string
    {
        return 'Déplace une candidature vers un nouveau statut (stage) dans le pipeline.';
    }

    protected function getParameters(): array
    {
        return [
            'application_id' => ['type' => 'integer', 'description' => 'ID de la candidature'],
            'new_status' => ['type' => 'string', 'description' => 'Nouveau statut'],
        ];
    }

    protected function getRequired(): array
    {
        return ['application_id', 'new_status'];
    }

    public function execute(array $args, object $user): \App\AI\Domain\ValueObject\ToolOutput
    {
        $validStatuses = ['PENDING', 'REVIEWING', 'INTERVIEW', 'OFFER', 'HIRED', 'REJECTED'];
        $newStatus = \strtoupper($args['new_status']);

        if (!\in_array($newStatus, $validStatuses)) {
            return $this->createOutput(
                "Statut invalide: {$newStatus}. Statuts possibles: " . \implode(', ', $validStatuses),
            );
        }

        $application = $this->em->find(\App\Entity\Recrutement\Application::class, $args['application_id']);

        if ($application === null) {
            return $this->createOutput("Candidature non trouvée: {$args['application_id']}");
        }

        $oldStatus = $application->getStatus();
        $summary = "Déplacement de statut demandé: {$oldStatus} → {$newStatus}. Confirmation requise.";

        return $this->createOutput($summary, [
            'type' => 'stage_move',
            'application_id' => $args['application_id'],
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'candidate_name' => $application->getCandidateName(),
        ], true);
    }
}