<?php

declare(strict_types=1);

namespace App\Controller\AI;

use App\AI\Core\ChangesetManager;
use App\Repository\AI\PendingChangesetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/rh/ai/changeset')]
class ChangesetController extends AbstractController
{
    #[Route('/{id}/confirm', name: 'app_rh_ai_changeset_confirm', methods: ['POST'])]
    public function confirm(
        string $id,
        ChangesetManager $changesetManager,
        LoggerInterface $logger,
    ): JsonResponse {
        try {
            $user = $this->getUser();
            if ($user === null) {
                return new JsonResponse(['error' => 'User not authenticated'], 401);
            }

            $changesetManager->confirm($id, $user);

            $logger->info('Changeset confirmed', [
                'changeset_id' => $id,
                'user_id' => method_exists($user, 'getId') ? $user->getId() : null,
            ]);

            return new JsonResponse(['status' => 'confirmed', 'id' => $id]);
        } catch (\Throwable $e) {
            $logger->error('Changeset confirm failed', [
                'changeset_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}/revert', name: 'app_rh_ai_changeset_revert', methods: ['POST'])]
    public function revert(
        string $id,
        ChangesetManager $changesetManager,
        LoggerInterface $logger,
    ): JsonResponse {
        try {
            $changesetManager->revert($id);

            $logger->info('Changeset reverted', [
                'changeset_id' => $id,
            ]);

            return new JsonResponse(['status' => 'reverted', 'id' => $id]);
        } catch (\Throwable $e) {
            $logger->error('Changeset revert failed', [
                'changeset_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }
}