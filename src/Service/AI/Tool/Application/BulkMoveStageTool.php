<?php

namespace App\Service\AI\Tool\Application;

use App\Service\AI\Tool\ToolInterface;
use App\Repository\Recrutement\ApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class BulkMoveStageTool implements ToolInterface
{
    public function __construct(
        private ApplicationRepository $applicationRepository,
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {}

    public function getName(): string
    {
        return 'bulk_move_stage';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'bulk_move_stage',
            'description' => 'Moves multiple candidate applications to a new recruitment stage simultaneously.',
            'parameters' => [
                'type' => 'object',
                'properties' => (object)[
                    'application_ids' => [
                        'type' => 'array',
                        'items' => ['type' => 'integer'],
                        'description' => 'List of application IDs to move'
                    ],
                    'new_status' => [
                        'type' => 'string',
                        'enum' => ['PENDING', 'REVIEWING', 'INTERVIEW', 'OFFER', 'HIRED', 'REJECTED'],
                        'description' => 'The destination status'
                    ]
                ],
                'required' => ['application_ids', 'new_status'],
                'additionalProperties' => false
            ]
        ];
    }

    public function execute(array $args): mixed
    {
        $user = $this->security->getUser();
        if (!$user) return ['error' => 'Non authentifié'];

        $ids = $args['application_ids'];
        $newStatus = $args['new_status'];
        
        $moved = [];
        $failed = [];

        foreach ($ids as $id) {
            $app = $this->applicationRepository->find($id);
            
            // Check ownership (security)
            if ($app && $app->getJobOffer()?->getCreatedBy() === (method_exists($user, 'getId') ? $user->getId() : null)) {
                $app->setStatus($newStatus);
                $moved[] = $app->getCandidateName();
            } else {
                $failed[] = "ID #$id (Non trouvé ou non autorisé)";
            }
        }

        $this->entityManager->flush();

        return [
            'status' => 'success',
            'moved_count' => count($moved),
            'candidates_moved' => $moved,
            'errors' => $failed,
            'message' => count($moved) . " candidats ont été déplacés vers l'étape " . $newStatus . "."
        ];
    }
}
