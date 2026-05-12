<?php

declare(strict_types=1);

namespace App\Controller\AI;

use App\AI\Core\AgentOrchestrator;
use App\AI\Infrastructure\ChatMessage;
use App\AI\Infrastructure\ConversationContext;
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

        if ($data === null) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $message = $data['message'] ?? '';
        $sessionId = $data['session_id'] ?? ($request->getSession()->getId() ?: bin2hex(random_bytes(8)));

        if (empty($message)) {
            return new JsonResponse(['error' => 'Message is empty'], 400);
        }

        try {
            $user = $this->getUser();
            if ($user === null) {
                return new JsonResponse(['error' => 'User not authenticated'], 401);
            }

            $conversationContext = new ConversationContext(
                messages: [new ChatMessage('user', $message)],
                user: $user,
                sessionId: $sessionId,
            );

            $result = $orchestrator->process($conversationContext);

            return new JsonResponse([
                'message' => $result->message,
                'ui_payload' => $result->uiPayload,
                'pending_changesets' => $result->pendingChangesets,
                'tool_calls' => $result->toolCalls,
                'plan' => $result->plan,
                'completed_steps' => $result->completedSteps,
                'active_job' => $result->activeJob,
                'candidates' => $result->candidates,
                'interviews' => $result->interviews,
                'candidates_analyzed' => $result->candidatesAnalyzed,
                'interviews_planned' => $result->interviewsPlanned,
                'request_id' => $requestId,
            ]);
        } catch (\Throwable $e) {
            $logger->error('AI chat failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'message' => "Le chatbot a rencontré une erreur. Réessayez dans quelques instants.\n\nRequest ID: `{$requestId}`",
                'validation_error' => $e->getMessage(),
                'request_id' => $requestId,
            ], 200);
        }
    }

    #[Route('/interface', name: 'app_ai_interface', methods: ['GET'])]
    public function interface(): \Symfony\Component\HttpFoundation\Response
    {
        return $this->render('ai/chat.html.twig');
    }

    #[Route('/chat/clear', name: 'app_rh_ai_chat_clear', methods: ['POST'])]
    public function clear(Request $request, \App\AI\Core\ConversationMemory $memory): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $sessionId = $data['session_id'] ?? $request->getSession()->getId();

        if ($sessionId) {
            $memory->clear($sessionId);
        }

        return new JsonResponse(['status' => 'cleared', 'session_id' => $sessionId]);
    }
}
