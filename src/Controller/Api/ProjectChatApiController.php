<?php

namespace App\Controller\Api;

use App\Security\DbUser;
use App\Service\Projet\ProjectChatRoomService;
use App\Service\Projet\ProjectCollaboratorService;
use App\Service\Projet\ProjectService;
use App\Service\Shared\MatrixService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/projects', name: 'api_project_chat_')]
final class ProjectChatApiController extends AbstractController
{
    public function __construct(
        private readonly ProjectService $projectService,
        private readonly ProjectCollaboratorService $collaboratorService,
        private readonly ProjectChatRoomService $chatRoomService,
        private readonly MatrixService $matrixService,
        private readonly LoggerInterface $logger,
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

        $matrixError = $this->matrixService->getLastError();
        if ($matrixError !== null) {
            return $this->buildMatrixErrorResponse('Impossible de charger les messages du chat.', $matrixError);
        }

        $messages = $result['messages'];
        foreach ($messages as &$message) {
            $url = (string) ($message['url'] ?? '');
            if (!str_starts_with($url, 'mxc://')) {
                continue;
            }

            $message['download_url'] = $this->generateUrl('api_project_chat_media', ['projectId' => $projectId])
                . '?mxc=' . rawurlencode($url);
        }
        unset($message);

        return $this->json([
            'success' => true,
            'messages' => $messages,
            'next' => $result['next'],
        ]);
    }

    #[Route('/{projectId}/chat/media', name: 'media', requirements: ['projectId' => '\\d+'], methods: ['GET'])]
    public function media(int $projectId, Request $request): Response
    {
        if ($this->getAuthorizedProject($projectId) === null) {
            return $this->json(['success' => false, 'message' => 'Acces refuse.'], 403);
        }

        $mxc = trim((string) $request->query->get('mxc', ''));
        if ($mxc === '') {
            return $this->json(['success' => false, 'message' => 'Parametre mxc manquant.'], 422);
        }

        $file = $this->matrixService->downloadMedia($mxc);
        if ($file === null) {
            $detail = $this->matrixService->getLastError();
            return $this->buildMatrixErrorResponse('Impossible de telecharger le fichier Matrix.', $detail);
        }

        $response = new Response($file['content']);
        $response->headers->set('Content-Type', $file['mime']);
        $response->headers->set('Content-Disposition', 'inline; filename="' . addcslashes($file['filename'], '"\\') . '"');

        return $response;
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
            return $this->buildMatrixErrorResponse('Echec envoi message Matrix.', $detail);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/{projectId}/chat/react', name: 'react', requirements: ['projectId' => '\\d+'], methods: ['POST'])]
    public function react(int $projectId, Request $request): JsonResponse
    {
        if ($this->getAuthorizedProject($projectId) === null) {
            return $this->json(['success' => false, 'message' => 'Acces refuse.'], 403);
        }

        $eventId = trim((string) $request->request->get('eventId', ''));
        $reaction = trim((string) $request->request->get('reaction', ''));
        if ($eventId === '' || $reaction === '') {
            return $this->json(['success' => false, 'message' => 'Reaction invalide.'], 422);
        }

        $roomId = $this->chatRoomService->getRoomIdByProject($projectId);
        if ($roomId === null) {
            return $this->json(['success' => false, 'message' => 'Chat non initialise.'], 409);
        }

        $ok = $this->matrixService->sendReaction($roomId, $eventId, $reaction);
        if (!$ok) {
            $detail = $this->matrixService->getLastError();
            return $this->buildMatrixErrorResponse('Echec envoi reaction Matrix.', $detail);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/{projectId}/chat/upload', name: 'upload', requirements: ['projectId' => '\\d+'], methods: ['POST'])]
    public function upload(int $projectId, Request $request): JsonResponse
    {
        $debug = [
            'projectId' => $projectId,
            'stage' => 'start',
        ];

        $project = $this->getAuthorizedProject($projectId);
        if ($project === null) {
            $this->logger->warning('Chat upload refused: unauthorized.', $debug);
            return $this->json(['success' => false, 'message' => 'Acces refuse.'], 403);
        }

        $debug['stage'] = 'file_validation';

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            $this->logger->warning('Chat upload failed: file missing.', $debug);
            return $this->json(['success' => false, 'message' => 'Fichier manquant.'], 422);
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            $debug['upload_error'] = $file->getError();
            $this->logger->warning('Chat upload failed: invalid upload error code.', $debug);
            return $this->json(['success' => false, 'message' => 'Upload invalide.'], 422);
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > 15 * 1024 * 1024) {
            $debug['size'] = $size;
            $this->logger->warning('Chat upload failed: invalid size.', $debug);
            return $this->json(['success' => false, 'message' => 'Le fichier doit faire entre 1 octet et 15 Mo.'], 422);
        }

        $debug['file_name'] = $file->getClientOriginalName();
        $debug['mime'] = $this->resolveUploadedFileMimeType($file);
        $debug['size'] = $size;
        $debug['stage'] = 'resolve_room';

        $roomId = $this->chatRoomService->getRoomIdByProject($projectId);
        if ($roomId === null) {
            $debug['stage'] = 'create_room';
            $roomId = $this->matrixService->createProjectRoom((string) ($project['name'] ?? ('Projet #' . $projectId)));
            if ($roomId === null) {
                $detail = $this->matrixService->getLastError();
                $debug['matrix_error'] = $detail;
                $this->logger->error('Chat upload failed: unable to create room.', $debug);
                return $this->buildMatrixErrorResponse('Impossible de creer la room Matrix.', $detail);
            }

            $this->chatRoomService->saveRoomId($projectId, $roomId);
        }

        $debug['roomId'] = $roomId;

        $debug['stage'] = 'matrix_upload';
        $matrixUploadError = null;
        $media = $this->matrixService->uploadMedia($file);
        if ($media === null) {
            $matrixUploadError = $this->matrixService->getLastError();
            $debug['matrix_upload_error'] = $matrixUploadError;
            $this->logger->warning('Chat upload: Matrix media upload failed, trying local fallback.', $debug);
        }

        /** @var DbUser $user */
        $user = $this->getUser();
        $sender = $user->getFullName() ?: $user->getUsername();

        if (is_array($media)) {
            $debug['stage'] = 'matrix_send_media';
            $mime = (string) $media['mime'];
            $msgType = $this->resolveMessageTypeByMime($mime);

            $ok = $this->matrixService->sendMediaMessage($roomId, $sender, [
                'mxc' => (string) $media['mxc'],
                'url' => (string) $media['download_url'],
                'name' => (string) $media['name'],
                'mime' => $mime,
                'size' => (int) $media['size'],
            ], $msgType);

            if ($ok) {
                $this->logger->info('Chat upload success via Matrix media.', $debug);
                return $this->json([
                    'success' => true,
                    'msgType' => $msgType,
                    'fileName' => (string) $media['name'],
                ]);
            }

            $matrixUploadError = $this->matrixService->getLastError();
            $debug['matrix_send_error'] = $matrixUploadError;
            $this->logger->warning('Chat upload: Matrix media send failed, trying local fallback.', $debug);
        }

        // Fallback robuste: stockage local + publication d'un lien dans le chat.
        $debug['stage'] = 'local_fallback_store';
        $localMedia = $this->storeChatFileLocally($file);
        if ($localMedia === null) {
            $debug['final_error'] = $matrixUploadError;
            $this->logger->error('Chat upload failed: local fallback store failed.', $debug);
            return $this->buildMatrixErrorResponse('Echec upload fichier Matrix.', $matrixUploadError);
        }

        $debug['stage'] = 'local_fallback_send_link';
        $relativeUrl = (string) $localMedia['url'];
        $publicUrl = str_starts_with($relativeUrl, 'http://') || str_starts_with($relativeUrl, 'https://')
            ? $relativeUrl
            : rtrim($request->getSchemeAndHttpHost(), '/') . $relativeUrl;

        $ok = $this->matrixService->sendMessage(
            $roomId,
            $sender,
            'Fichier: ' . (string) $localMedia['name'] . ' - ' . $publicUrl
        );

        if (!$ok) {
            $detail = $this->matrixService->getLastError();
            $debug['final_error'] = $detail ?: $matrixUploadError;
            $this->logger->error('Chat upload failed: local fallback send message failed.', $debug);
            return $this->buildMatrixErrorResponse('Echec envoi fichier Matrix.', $detail ?: $matrixUploadError);
        }

        $this->logger->info('Chat upload success via local fallback.', $debug);

        return $this->json([
            'success' => true,
            'msgType' => 'm.file',
            'fileName' => (string) $localMedia['name'],
        ]);
    }

    #[Route('/{projectId}/chat/video-call', name: 'video_call', requirements: ['projectId' => '\\d+'], methods: ['POST'])]
    public function videoCall(int $projectId): JsonResponse
    {
        $project = $this->getAuthorizedProject($projectId);
        if ($project === null) {
            return $this->json(['success' => false, 'message' => 'Acces refuse.'], 403);
        }

        $roomId = $this->chatRoomService->getRoomIdByProject($projectId);
        if ($roomId === null) {
            $roomId = $this->matrixService->createProjectRoom((string) ($project['name'] ?? ('Projet #' . $projectId)));
            if ($roomId === null) {
                $detail = $this->matrixService->getLastError();
                return $this->buildMatrixErrorResponse('Impossible de creer la room Matrix.', $detail);
            }

            $this->chatRoomService->saveRoomId($projectId, $roomId);
        }

        $callSlug = 'hrflow-project-' . $projectId . '-' . substr(sha1((string) microtime(true) . '-' . random_int(1000, 9999)), 0, 8);
        $callUrl = 'https://meet.jit.si/' . $callSlug;

        $ok = $this->matrixService->sendMessage($roomId, 'Systeme', 'Appel video: ' . $callUrl);
        if (!$ok) {
            $detail = $this->matrixService->getLastError();
            return $this->buildMatrixErrorResponse('Echec creation appel video.', $detail);
        }

        return $this->json([
            'success' => true,
            'url' => $callUrl,
        ]);
    }

    /** @return array<string, mixed>|null */
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
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return $project;
        }

        if (in_array('ROLE_RH', $roles, true)) {
            // L'interface RH permet deja d'ouvrir un projet sans filtrage proprietaire strict.
            return $project;
        }

        if (in_array('ROLE_EMPLOYEE', $roles, true)) {
            return $this->collaboratorService->isCollaborator($projectId, $user->getId()) ? $project : null;
        }

        return null;
    }

    private function buildMatrixErrorResponse(string $fallbackMessage, ?string $detail): JsonResponse
    {
        $status = $this->matrixService->getLastErrorStatus() ?? 502;
        $errCode = $this->matrixService->getLastErrorCode();

        // Evite d'exposer en boucle l'erreur technique brute quand le token bot est invalide.
        if ($status === 401 || $errCode === 'M_UNKNOWN_TOKEN') {
            return $this->json([
                'success' => false,
                'message' => 'Service chat indisponible temporairement. Le bot Matrix doit etre reconnecte.',
            ], 503);
        }

        return $this->json([
            'success' => false,
            'message' => $fallbackMessage . ($detail ? ' ' . $detail : ''),
        ], $status >= 400 && $status <= 599 ? $status : 502);
    }

    private function resolveMessageTypeByMime(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) {
            return 'm.image';
        }

        if (str_starts_with($mime, 'video/')) {
            return 'm.video';
        }

        if (str_starts_with($mime, 'audio/')) {
            return 'm.audio';
        }

        return 'm.file';
    }

    /**
     * @return array{url:string,name:string,mime:string,size:int}|null
     */
    private function storeChatFileLocally(UploadedFile $file): ?array
    {
        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $targetDir = $projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'chat';

        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return null;
        }

        $originalName = $file->getClientOriginalName() ?: 'fichier';
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName) ?: 'fichier';
        try {
            $suffix = bin2hex(random_bytes(4));
        } catch (\Throwable) {
            $suffix = (string) mt_rand(100000, 999999);
        }

        $storedName = date('YmdHis') . '-' . $suffix . '-' . $safeName;

        try {
            $file->move($targetDir, $storedName);
        } catch (\Throwable) {
            return null;
        }

        return [
            'url' => '/uploads/chat/' . $storedName,
            'name' => $originalName,
            'mime' => $this->resolveUploadedFileMimeType($file),
            'size' => (int) $file->getSize(),
        ];
    }

    private function resolveUploadedFileMimeType(UploadedFile $file): string
    {
        try {
            $mime = $file->getMimeType();
            if ($mime !== null && $mime !== '') {
                return $mime;
            }
        } catch (\Throwable) {
            // Fallback when fileinfo is not available on the host.
        }

        $clientMime = $file->getClientMimeType();
        return $clientMime !== '' ? $clientMime : 'application/octet-stream';
    }
}

