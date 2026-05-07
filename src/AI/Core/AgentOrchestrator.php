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

        $intents = $this->intentRouter->classify($messages);
        $entities = $this->intentRouter->detectEntities($messages);
        $selectedTools = $this->intentRouter->selectTools($intents, $this->toolRegistry, $entities);
        $scopedTools = \array_slice($selectedTools, 0, self::MAX_TOOLS_PER_REQUEST);

        $intentValue = !empty($intents) ? $intents[0]->value : null;
        $systemPrompt = $this->contextProvider->buildSystemPrompt($user, $intentValue, $entities);

        $loopResult = $this->runAgenticLoop($messages, $scopedTools, $user, $sessionId, $systemPrompt);
        $finalMessage = $loopResult['message'];
        $toolCalls = $loopResult['toolCalls'];
        $toolResults = $loopResult['toolResults'];
        $pendingChangesets = $loopResult['pendingChangesets'];
        $enrichedMessages = $loopResult['messages'];

        $uiPayload = $this->buildUiPayload($toolCalls, $toolResults);

        $this->memory->save($sessionId, $enrichedMessages);

        $formattedToolCalls = \array_map(function (ToolCall $tc, array $result) {
            $ui = $result['uiPayload'] ?? [];
            return [
                'tool_name' => $tc->name,
                'status' => 'done',
                'details' => $ui,
                'args' => $tc->arguments,
                'download_url' => $ui['download_url'] ?? $ui['file_path'] ?? null,
                'chart_data' => $ui['chart_data'] ?? null,
                'chart_config' => $ui['chart_config'] ?? null,
                'file_name' => $ui['file_name'] ?? null,
                'export_type' => $ui['export_type'] ?? null,
                'ui_type' => $ui['type'] ?? null,
            ];
        }, $toolCalls, $toolResults);

        $planSteps = $this->buildPlanSteps($toolCalls);

        $aggregates = $this->aggregateToolResults($toolResults);

        return new AgentResponse(
            message: $finalMessage->content ?: 'Traitement terminé.',
            uiPayload: $uiPayload,
            pendingChangesets: $pendingChangesets,
            toolCalls: $formattedToolCalls,
            plan: $planSteps,
            completedSteps: \count($planSteps),
            activeJob: $aggregates['activeJob'],
            candidates: $aggregates['candidates'],
            interviews: $aggregates['interviews'],
            candidatesAnalyzed: $aggregates['candidatesAnalyzed'],
            interviewsPlanned: $aggregates['interviewsPlanned'],
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
     * @return array{message: ChatMessage, toolCalls: ToolCall[], toolResults: array[], pendingChangesets: PendingChangeset[], messages: ChatMessage[]}
     */
    private function runAgenticLoop(array $messages, array $tools, object $user, string $sessionId, string $systemPrompt = ''): array
    {
        $iteration = 0;
        $pendingChangesets = [];
        $allToolCalls = [];
        $allToolResults = [];

        while ($iteration < self::MAX_LOOP_ITERATIONS) {
            ++$iteration;

            $request = new ChatRequest(
                messages: $messages,
                systemPrompt: $systemPrompt,
                tools: $tools,
                maxTools: self::MAX_TOOLS_PER_REQUEST,
            );

            $response = $this->llmClient->chat($request);

            if (\count($response->toolCalls) === 0) {
                $messages[] = new ChatMessage('model', $response->content);
                return [
                    'message' => new ChatMessage('model', $response->content),
                    'toolCalls' => $allToolCalls,
                    'toolResults' => $allToolResults,
                    'pendingChangesets' => $pendingChangesets,
                    'messages' => $messages,
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
                $allToolResults[] = [
                    'llmSummary' => $toolResult->llmSummary,
                    'uiPayload' => $toolResult->uiPayload,
                ];

                if ($toolResult->hasPendingChange) {
                    $changeset = $this->changesetManager->stage(
                        $toolCall,
                        ['result' => $toolResult, 'sessionId' => $sessionId],
                        $user,
                    );
                    $pendingChangesets[] = $changeset;
                }

                $messages[] = new ChatMessage(
                    role: 'model',
                    content: '',
                    toolCallId: $toolCall->id,
                    toolCallName: $toolCall->name,
                    toolCallArgs: $toolCall->arguments,
                );
                $messages[] = new ChatMessage(
                    role: 'user',
                    content: $toolResult->llmSummary,
                    toolResponse: $toolResult->uiPayload,
                    toolCallName: $toolCall->name,
                );
            }
        }

        return [
            'message' => new ChatMessage('model', 'Nombre maximum d\'itérations atteint.'),
            'toolCalls' => $allToolCalls,
            'toolResults' => $allToolResults,
            'pendingChangesets' => $pendingChangesets,
            'messages' => $messages,
        ];
    }

    /**
     * @param ChatMessage[] $messages
     * @return ChatMessage[]
     */
    private function trimMessages(array $messages): array
    {
        if (\count($messages) <= 10) {
            return $messages;
        }

        $recent = \array_slice($messages, -8);
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
     * @param array[] $toolResults
     */
    private function buildUiPayload(array $toolCalls, array $toolResults): array
    {
        $payload = [];
        foreach ($toolCalls as $i => $tc) {
            $result = $toolResults[$i] ?? [];
            $payload[] = [
                'name' => $tc->name,
                'status' => 'done',
                'body' => $result['llmSummary'] ?? 'Outil exécuté avec succès.',
                'uiPayload' => $result['uiPayload'] ?? [],
            ];
        }
        return $payload;
    }

    /**
     * @param ToolCall[] $toolCalls
     * @return string[]
     */
    private function buildPlanSteps(array $toolCalls): array
    {
        $steps = [];

        foreach ($toolCalls as $tc) {
            $action = $tc->arguments['action'] ?? null;
            $toolLabel = match ($tc->name) {
                'manage_job_offers' => 'offres d\'emploi',
                'manage_applications' => 'candidatures',
                'manage_interviews' => 'entretiens',
                default => $tc->name,
            };

            $actionLabel = match ($action) {
                'list' => 'Lister',
                'search' => 'Rechercher',
                'view' => 'Consulter',
                'create' => 'Créer',
                'update' => 'Modifier',
                'change_status' => 'Changer le statut',
                'move' => 'Déplacer',
                'rank' => 'Classer',
                'schedule' => 'Planifier',
                'cancel' => 'Annuler',
                'delete' => 'Supprimer',
                default => 'Exécuter',
            };

            $details = '';

            if (isset($tc->arguments['id'])) {
                $details = ' #' . $tc->arguments['id'];
            } elseif (isset($tc->arguments['job_offer_id'])) {
                $details = ' pour l\'offre #' . $tc->arguments['job_offer_id'];
            } elseif (isset($tc->arguments['application_id'])) {
                $details = ' pour la candidature #' . $tc->arguments['application_id'];
            }

            $steps[] = "{$actionLabel} {$toolLabel}{$details}";
        }

        if (empty($steps)) {
            $steps[] = 'Analyser la requête';
        }

        return $steps;
    }

    /**
     * Extract aggregates from tool results for the response.
     *
     * @param array[] $toolResults
     * @return array{activeJob: ?array, candidates: array, interviews: array, candidatesAnalyzed: int, interviewsPlanned: int}
     */
    private function aggregateToolResults(array $toolResults): array
    {
        $activeJob = null;
        $candidates = [];
        $interviews = [];
        $candidatesAnalyzed = 0;
        $interviewsPlanned = 0;

        foreach ($toolResults as $result) {
            $ui = $result['uiPayload'] ?? [];
            $uiType = $ui['type'] ?? null;

            if ($uiType === 'applications_table' && isset($ui['data'])) {
                $candidates = array_merge($candidates, $ui['data']);
                $candidatesAnalyzed += count($ui['data']);
            }
            if ($uiType === 'candidates_table' && isset($ui['data'])) {
                $candidates = array_merge($candidates, $ui['data']);
                $candidatesAnalyzed += count($ui['data']);
            }
            if ($uiType === 'candidate_grid' && isset($ui['candidates'])) {
                $candidates = array_merge($candidates, $ui['candidates']);
                $candidatesAnalyzed = max($candidatesAnalyzed, count($ui['candidates']));
            }
            if ($uiType === 'ranking' && isset($ui['data'])) {
                $candidates = array_merge($candidates, $ui['data']);
                $candidatesAnalyzed = max($candidatesAnalyzed, count($ui['data']));
            }
            if ($uiType === 'interviews_table' && isset($ui['data'])) {
                $interviews = array_merge($interviews, $ui['data']);
            }
            if ($uiType === 'interview_scheduled') {
                $interviewsPlanned++;
            }
            if (isset($ui['candidates_analyzed'])) {
                $candidatesAnalyzed = max($candidatesAnalyzed, (int) $ui['candidates_analyzed']);
            }
            if (isset($ui['interviews_planned'])) {
                $interviewsPlanned += (int) $ui['interviews_planned'];
            }
            if (isset($ui['job_offer']) && is_array($ui['job_offer'])) {
                $activeJob = $ui['job_offer'];
            }
            if (\in_array($uiType, ['job_offers_table', 'job_offers_list'], true) && isset($ui['data']) && count($ui['data']) > 0) {
                $activeJob = [
                    'title' => $ui['data'][0]['title'] ?? null,
                    'location' => $ui['data'][0]['location'] ?? null,
                    'applications' => count($ui['data']),
                ];
            }
        }

        return [
            'activeJob' => $activeJob,
            'candidates' => $candidates,
            'interviews' => $interviews,
            'candidatesAnalyzed' => $candidatesAnalyzed,
            'interviewsPlanned' => $interviewsPlanned,
        ];
    }
}
