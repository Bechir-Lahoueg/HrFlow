<?php

namespace App\Service\AI\Tool\Application;

use App\Service\AI\Tool\ToolInterface;
use App\Repository\Recrutement\ApplicationRepository;
use App\Security\DbUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class MoveStageTool implements ToolInterface
{
    public function __construct(
        private ApplicationRepository $applicationRepository,
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {}

    public function getName(): string
    {
        return 'move_candidate_stage';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'move_candidate_stage',
            'description' => 'Advances a candidate application to a new stage in the recruitment pipeline.',
            'parameters' => [
                'type' => 'object',
                'properties' => (object)[
                    'application_id' => [
                        'type' => 'integer',
                        'description' => 'The ID of the candidate application'
                    ],
                    'new_status' => [
                        'type' => 'string',
                        'enum' => ['PENDING', 'REVIEWING', 'INTERVIEW', 'OFFER', 'HIRED', 'REJECTED'],
                        'description' => 'The new status for the application'
                    ]
                ],
                'required' => ['application_id', 'new_status'],
                'additionalProperties' => false
            ]
        ];
    }

    public function execute(array $args): mixed
    {
        $user = $this->security->getUser();
        if (!$user instanceof DbUser) {
            return ['error' => 'Utilisateur non connecté'];
        }

        // Verify ownership - only allow moving applications for jobs the RH created
        $app = $this->applicationRepository->findOneByRh($args['application_id'], $user);
        
        if (!$app) {
            return ['error' => "Application #{$args['application_id']} non trouvée ou vous n'avez pas l'autorisation."];
        }

        $oldStatus = $app->getStatus();
        $app->setStatus($args['new_status']);
        
        $this->entityManager->flush();

        return [
            'application_id' => $app->getId(),
            'candidate' => $app->getCandidateName(),
            'old_status' => $oldStatus,
            'new_status' => $app->getStatus(),
            'message' => "✅ {$app->getCandidateName()} est maintenant {$this->getStatusLabel($args['new_status'])}."
        ];
    }

    private function getStatusLabel(string $status): string
    {
        return match($status) {
            'PENDING' => 'En attente',
            'REVIEWING' => 'En revue',
            'INTERVIEW' => 'Entretien',
            'OFFER' => 'Offre',
            'HIRED' => 'Recruté(e)',
            'REJECTED' => 'Rejeté(e)',
            default => $status
        };
    }
}
