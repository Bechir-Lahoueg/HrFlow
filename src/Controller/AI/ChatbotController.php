<?php

namespace App\Controller\AI;

use App\Service\AI\AgentOrchestrator;
use App\Service\AI\SessionMemory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/rh/ai')]
class ChatbotController extends AbstractController
{
    #[Route('/chat', name: 'app_rh_ai_chat', methods: ['POST'])]
    public function chat(Request $request, AgentOrchestrator $orchestrator, LoggerInterface $logger): JsonResponse
    {
        $requestId = bin2hex(random_bytes(8));
        $data = json_decode($request->getContent(), true);
        $message = $data['message'] ?? '';
        $history = $data['history'] ?? [];
        $sessionId = $data['session_id'] ?? null;

        if (empty($message)) {
            $res = new JsonResponse(['error' => 'Message is empty', 'request_id' => $requestId], 400);
            $res->headers->set('X-Request-Id', $requestId);
            return $res;
        }

        try {
            $logger->info('AI chat request', [
                'request_id' => $requestId,
                'path' => $request->getPathInfo(),
                'user_id' => $this->getUser() && method_exists($this->getUser(), 'getId') ? $this->getUser()->getId() : null,
                'session_id' => $sessionId,
                'history_count' => is_array($history) ? count($history) : 0,
            ]);

            $result = $orchestrator->chat($message, $history, $requestId, $sessionId);
            $result['request_id'] = $requestId;

            $res = new JsonResponse($result);
            $res->headers->set('X-Request-Id', $requestId);
            return $res;
        } catch (\Throwable $e) {
            $logger->error('AI chat failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);

            // Return a user-friendly payload (avoid hard 500 breaking UI).
            $payload = [
                'message' => "Le chatbot a rencontré une erreur côté serveur. Réessayez dans quelques instants.\n\nRequest ID: `{$requestId}`",
                'validation_error' => $e->getMessage(),
                'plan' => [],
                'completed_steps' => 0,
                'tool_calls' => [],
                'active_job' => null,
                'candidates' => [],
                'candidates_analyzed' => 0,
                'interviews_planned' => 0,
                'request_id' => $requestId,
            ];
            if ($this->getParameter('kernel.debug')) {
                $payload['debug'] = ['trace' => $e->getTraceAsString()];
            }

            $res = new JsonResponse($payload, 200);
            $res->headers->set('X-Request-Id', $requestId);
            return $res;
        }
    }

    #[Route('/interface', name: 'app_ai_interface', methods: ['GET'])]
    public function interface()
    {
        return $this->render('ai/chat.html.twig');
    }

    #[Route('/chat/clear', name: 'app_rh_ai_chat_clear', methods: ['POST'])]
    public function clear(Request $request, SessionMemory $sessionMemory, LoggerInterface $logger): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $sessionId = $data['session_id'] ?? null;

        if ($sessionId) {
            $sessionMemory->delete('chat:' . $sessionId);
            $logger->info('AI chat session cleared', [
                'session_id' => $sessionId,
                'user_id' => $this->getUser() && method_exists($this->getUser(), 'getId') ? $this->getUser()->getId() : null,
            ]);
        }

        return new JsonResponse(['status' => 'cleared', 'session_id' => $sessionId]);
    }
}
