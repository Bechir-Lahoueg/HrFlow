<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Contract\LlmClientInterface;
use App\AI\Contract\ToolInterface;
use App\AI\Contract\ToolRegistryInterface;
use App\AI\Domain\Enum\IntentType;
use App\AI\Domain\ValueObject\PendingChangeset;
use App\AI\Infrastructure\AgentResponse;
use App\AI\Infrastructure\ChatMessage;
use App\AI\Infrastructure\ChatRequest;
use App\AI\Infrastructure\ConversationContext;
use App\AI\Infrastructure\ToolCall;

final class AgentOrchestrator
{
    private const MAX_LOOP_ITERATIONS = 5;
    private const MAX_TOOLS_PER_REQUEST = 5;

    public function __construct(
        private readonly LlmClientInterface $llmClient,
        private readonly ToolRegistryInterface $toolRegistry,
        private readonly ToolValidator $toolValidator,
        private readonly ConversationMemory $memory,
        private readonly RecruitmentContextProvider $contextProvider,
        private readonly IntentRouter $intentRouter,
        private readonly ChangesetManager $changesetManager,
    ) {}

    public function process(ConversationContext $conversationContext): AgentResponse
    {
        $messages = $conversationContext->messages;
        $user = $conversationContext->user;
        $sessionId = $conversationContext->sessionId;

        $history = $this->memory->load($sessionId);
        $allMessages = [...$history, ...$messages];

        if (\count($history) > 0) {
            $messages = $this->trimMessages($allMessages);
        }

        $lastUserMsg = $this->getLastUserMessage($messages);
        if ($this->isGreeting($lastUserMsg)) {
            return $this->handleGreeting($lastUserMsg, $user);
        }

        $intent = $this->intentRouter->classify($messages);
        $selectedTools = $this->intentRouter->selectTools($intent, $this->toolRegistry);
        $scopedTools = \array_slice($selectedTools, 0, self::MAX_TOOLS_PER_REQUEST);

        $systemPrompt = $this->contextProvider->buildSystemPrompt($user, $intent?->value);

        $loopResult = $this->runAgenticLoop($messages, $scopedTools, $user, $sessionId);
        $finalMessage = $loopResult['message'];
        $toolCalls = $loopResult['toolCalls'];
        $pendingChangesets = $loopResult['pendingChangesets'];

        $uiPayload = $this->buildUiPayload($toolCalls);
        $finalMessage->content = $finalMessage->content ?: 'Traitement terminé.';

        $this->memory->save($sessionId, $messages);

        return new AgentResponse(
            message: $finalMessage->content,
            uiPayload: $uiPayload,
            pendingChangesets: \array_map(
                fn(PendingChangeset $c) => [
                    'id' => $c->id,
                    'tool' => $c->tool,
                    'action' => $c->action,
                ],
                $pendingChangesets,
            ),
            toolCalls: \array_map(
                fn(ToolCall $tc) => ['name' => $tc->name, 'args' => $tc->arguments],
                $toolCalls,
            ),
        );
    }

    private function handleGreeting(string $message, object $user): AgentResponse
    {
        $name = method_exists($user, 'getFirstName') ? $user->getFirstName() : null;
        $greeting = $name
            ? "Bonjour {$name} ! Je suis votre assistant recrutement. Comment puis-je vous aider ?"
            : "Bonjour ! Je suis votre assistant recrutement. Comment puis-je vous aider ?";

        return new AgentResponse(
            message: $greeting,
            uiPayload: [],
            pendingChangesets: [],
            toolCalls: [],
        );
    }

    private function isGreeting(string $message): bool
    {
        $lower = \strtolower(\trim($message));
        $greetings = ['bonjour', 'salut', 'hello', 'hi', 'coucou', 'bjr', 'good morning'];

        return \in_array($lower, $greetings) || (\strlen($lower) < 20 && \count(\array_filter(
            $greetings,
            fn($g) => \str_starts_with($lower, $g),
        )) > 0);
    }

    /**
     * @param ToolInterface[] $tools
     * @return array{message: ChatMessage, toolCalls: ToolCall[], pendingChangesets: PendingChangeset[]}
     */
    private function runAgenticLoop(array $messages, array $tools, object $user, string $sessionId): array
    {
        $iteration = 0;
        $pendingChangesets = [];
        $allToolCalls = [];

        while ($iteration < self::MAX_LOOP_ITERATIONS) {
            ++$iteration;

            $request = new ChatRequest(
                messages: $messages,
                systemPrompt: '',
                tools: $tools,
                maxTools: self::MAX_TOOLS_PER_REQUEST,
            );

            $response = $this->llmClient->chat($request);

            if (\count($response->toolCalls) === 0) {
                return [
                    'message' => new ChatMessage('model', $response->content),
                    'toolCalls' => $allToolCalls,
                    'pendingChangesets' => $pendingChangesets,
                ];
            }

            foreach ($response->toolCalls as $toolCall) {
                $this->toolValidator->validate($toolCall, $user);

                try {
                    $tool = $this->toolRegistry->get($toolCall->name);
                } catch (\InvalidArgumentException) {
                    $messages[] = new ChatMessage('user', "Outil non trouvé: {$toolCall->name}");
                    continue;
                }

                $toolResult = $tool->execute($toolCall->arguments, $user);
                $allToolCalls[] = $toolCall;

                if ($toolResult->hasPendingChange && $toolResult->pendingChangeset !== null) {
                    $changeset = $this->changesetManager->stage(
                        $toolCall,
                        ['result' => $toolResult, 'sessionId' => $sessionId],
                        $user,
                    );
                    $pendingChangesets[] = $changeset;
                }

                $messages[] = new ChatMessage('user', $toolResult->llmSummary);
            }
        }

        return [
            'message' => new ChatMessage('model', 'Nombre maximum d\'itérations atteint.'),
            'toolCalls' => $allToolCalls,
            'pendingChangesets' => $pendingChangesets,
        ];
    }

    /**
     * @param ChatMessage[] $messages
     * @return ChatMessage[]
     */
    private function trimMessages(array $messages): array
    {
        if (\count($messages) <= 20) {
            return $messages;
        }

        $recent = \array_slice($messages, -15);
        $summary = $this->memory->summarizeOld($messages);

        return [$summary, ...$recent];
    }

    /**
     * @param ChatMessage[] $messages
     */
    private function getLastUserMessage(array $messages): string
    {
        for ($i = \count($messages) - 1; $i >= 0; --$i) {
            if ($messages[$i]->role === 'user') {
                return $messages[$i]->content;
            }
        }
        return '';
    }

    /**
     * @param ToolCall[] $toolCalls
     */
    private function buildUiPayload(array $toolCalls): array
    {
        $payload = [];
        foreach ($toolCalls as $tc) {
            $payload[] = [
                'name' => $tc->name,
                'status' => 'done',
                'body' => 'Outil exécuté avec succès.',
            ];
        }
        return $payload;
    }
}