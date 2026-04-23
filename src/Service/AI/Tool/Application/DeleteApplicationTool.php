<?php

namespace App\Service\AI\Tool\Application;

use App\Repository\Recrutement\ApplicationRepository;
use App\Service\AI\Tool\ToolInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class DeleteApplicationTool implements ToolInterface
{
    public function __construct(
        private ApplicationRepository $applicationRepository,
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {}

    public function getName(): string
    {
        return 'delete_application';
    }

    public function getDefinition(): array
    {
        return [
            'name' => $this->getName(),
            'description' => "Supprime (soft delete) une candidature (propriétaire RH uniquement).",
            'parameters' => [
                'type' => 'object',
                'properties' => (object)[
                    'application_id' => ['type' => 'integer', 'description' => 'ID de la candidature'],
                ],
                'required' => ['application_id'],
                'additionalProperties' => false
            ]
        ];
    }

    public function execute(array $args): mixed
    {
        $user = $this->security->getUser();
        if (!$user) {
            return ['error' => 'Not authenticated'];
        }

        $id = (int) $args['application_id'];
        $app = $this->applicationRepository->findOneByRh($id, $user);
        if (!$app) {
            return ['error' => "Candidature #{$id} introuvable ou non autorisée."];
        }

        $app->setIsDeleted(true);
        $this->entityManager->flush();

        return [
            'status' => 'success',
            'application_id' => $id,
            'message' => "✅ Candidature #{$id} supprimée (soft delete).",
        ];
    }
}

