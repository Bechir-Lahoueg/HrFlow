<?php

namespace App\Controller\Api;

use App\Security\DbUser;
use App\Service\Projet\ProjectChatRoomService;
use App\Service\Projet\ProjectCollaboratorService;
use App\Service\Projet\ProjectService;
use App\Service\Shared\MatrixService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/projects', name: 'api_project_chat_')]
final class ProjectChatApiController extends AbstractController
{
    public function __construct(
        private readonly ProjectService $projectService,
        private readonly ProjectCollaboratorService $collaboratorService,
        private readonly ProjectChatRoomService $chatRoomService,
        private readonly MatrixService $matrixService,
    ) {
    }

    #[Route('/{projectId}/chat/bootstrap', name: 'bootstrap', requirements: ['projectId' => '\\d+'], methods: ['GET'])]
    public function bootstrap(int $projectId): JsonResponse
    {
        $project = $this->getAuthorizedProject($projectId);
        if ($project === null) {
            return $this->json(['success' => false, 'message' => 'Acces refuse.'], 403);
        }

        if (!$this->matrixService->isConfigured()) {
            return $this->json(['success' => false, 'message' => 'Matrix n\'est pas configure.'], 500);
        }

        $roomId = $this->chatRoomService->getRoomIdByProject($projectId);
        if ($roomId === null) {
            $roomId = $this->matrixService->createProjectRoom((string) ($project['name'] ?? ('Projet #' . $projectId)));
            if ($roomId === null) {
                $detail = $this->matrixService->getLastError();
                return $this->json(['success' => false, 'message' => 'Impossible de creer la room Matrix.' . ($detail ? ' ' . $detail : '')], 500);
            }

            $this->chatRoomService->saveRoomId($projectId, $roomId);
        }

        return $this->json([
            'success' => true,
            'roomId' => $roomId,
            'projectId' => $projectId,
        ]);
    }

    #[Route('/{projectId}/chat/messages', name: 'messages', requirements: ['projectId' => '\\d+'], methods: ['GET'])]
    public function messages(int $projectId, Request $request): JsonResponse
    {
        $project = $this->getAuthorizedProject($projectId);
        if ($project === null) {
            return $this->json(['success' => false, 'message' => 'Acces refuse.'], 403);
        }

        $roomId = $this->chatRoomService->getRoomIdByProject($projectId);
        if ($roomId === null) {
            return $this->json(['success' => true, 'messages' => [], 'next' => null]);
        }

        $from = trim((string) $request->query->get('from', ''));
        if ($from === '') {
            $from = null;
        }

        $limit = (int) $request->query->get('limit', 40);
        $result = $this->matrixService->getMessages($roomId, $from, $limit);

        return $this->json([
            'success' => true,
            'messages' => $result['messages'],
            'next' => $result['next'],
        ]);
    }

    #[Route('/{projectId}/chat/send', name: 'send', requirements: ['projectId' => '\\d+'], methods: ['POST'])]
    public function send(int $projectId, Request $request): JsonResponse
    {
        $project = $this->getAuthorizedProject($projectId);
        if ($project === null) {
            return $this->json(['success' => false, 'message' => 'Acces refuse.'], 403);
        }

        $message = trim((string) $request->request->get('message', ''));
        if ($message === '') {
            return $this->json(['success' => false, 'message' => 'Message vide.'], 422);
        }

        $roomId = $this->chatRoomService->getRoomIdByProject($projectId);
        if ($roomId === null) {
            $roomId = $this->matrixService->createProjectRoom((string) ($project['name'] ?? ('Projet #' . $projectId)));
            if ($roomId === null) {
                $detail = $this->matrixService->getLastError();
                return $this->json(['success' => false, 'message' => 'Impossible de creer la room Matrix.' . ($detail ? ' ' . $detail : '')], 500);
            }

            $this->chatRoomService->saveRoomId($projectId, $roomId);
        }

        /** @var DbUser $user */
        $user = $this->getUser();
        $sender = $user->getFullName() ?: $user->getUsername();

        $ok = $this->matrixService->sendMessage($roomId, $sender, $message);
        if (!$ok) {
            $detail = $this->matrixService->getLastError();
            return $this->json(['success' => false, 'message' => 'Echec envoi message Matrix.' . ($detail ? ' ' . $detail : '')], 500);
        }

        return $this->json(['success' => true]);
    }

    private function getAuthorizedProject(int $projectId): ?array
    {
        $project = $this->projectService->getProjectById($projectId);
        if (!$project) {
            return null;
        }

        $user = $this->getUser();
        if (!$user instanceof DbUser) {
            return null;
        }

        $roles = $user->getRoles();
        if (in_array('ROLE_RH', $roles, true)) {
            return ((int) ($project['rh_id'] ?? 0) === $user->getId()) ? $project : null;
        }

        if (in_array('ROLE_EMPLOYEE', $roles, true)) {
            return $this->collaboratorService->isCollaborator($projectId, $user->getId()) ? $project : null;
        }

        return null;
    }
}


