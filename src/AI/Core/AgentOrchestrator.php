<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Contract\LlmClientInterface;
use App\AI\Domain\DTO\ApplicationQuery;
use App\AI\Domain\DTO\CandidateQuery;
use App\AI\Domain\DTO\IntentDTO;
use App\AI\Domain\DTO\InterviewQuery;
use App\AI\Domain\DTO\PipelineQuery;
use App\AI\Domain\Enum\IntentType;
use App\AI\Infrastructure\AgentResponse;
use App\AI\Infrastructure\ChatMessage;
use App\AI\Infrastructure\ChatRequest;
use App\AI\Infrastructure\ConversationContext;
use App\Service\Recrutement\ApplicationService;
use App\Service\Recrutement\CandidateService;
use App\Service\Recrutement\InterviewService;
use App\Service\Recrutement\PipelineService;
use App\Service\Recrutement\ReportService;

final class AgentOrchestrator
{
    public function __construct(
        private readonly LlmClientInterface $llmClient,
        private readonly IntentParser $intentParser,
        private readonly PipelineService $pipelineService,
        private readonly CandidateService $candidateService,
        private readonly ApplicationService $applicationService,
        private readonly InterviewService $interviewService,
        private readonly ReportService $reportService,
        private readonly ChangesetManager $changesetManager,
        private readonly ConversationMemory $memory,
    ) {}

    public function process(ConversationContext $conversationContext): AgentResponse
    {
        $user = $conversationContext->user;
        $sessionId = $conversationContext->sessionId;

        $lastMessage = $this->getLastUserMessage($conversationContext->messages);

        if ($this->isGreeting($lastMessage)) {
            return $this->handleGreeting($lastMessage, $user);
        }

        $intent = $this->intentParser->parse($lastMessage);

        $result = $this->dispatch($intent, $user);

        $responseMessage = $this->generateResponse($intent, $result);

        return $this->buildAgentResponse($responseMessage, $result, $intent);
    }

    private function dispatch(IntentDTO $intent, object $user): ServiceResult
    {
        return match ($intent->intent) {
            IntentType::GREETING => new ServiceResult(
                data: [],
                message: 'Bonjour ! Comment puis-je vous aider ?',
            ),
            IntentType::PIPELINE_ANALYSIS => $this->handlePipelineAnalysis($intent),
            IntentType::CANDIDATE_ANALYSIS => $this->handleCandidateAnalysis($intent),
            IntentType::DATA_QUERY => $this->handleDataQuery($intent),
            IntentType::MUTATION => $this->handleMutation($intent, $user),
            IntentType::SCHEDULING => $this->handleScheduling($intent),
            IntentType::REPORT_GENERATION => $this->handleReportGeneration($intent),
            IntentType::UNKNOWN => new ServiceResult(
                data: [],
                message: "Je n'ai pas compris votre demande. Pouvez-vous reformuler ?",
            ),
        };
    }

    private function handlePipelineAnalysis(IntentDTO $intent): ServiceResult
    {
        $params = $intent->parameters;
        $query = new PipelineQuery(
            department: $params['department'] ?? null,
            dateRange: $params['time_range'] ?? $params['date_range'] ?? null,
            groupBy: $params['group_by'] ?? null,
            status: $params['status'] ?? null,
            jobOfferId: isset($params['job_offer_id']) ? (int) $params['job_offer_id'] : null,
        );

        $result = $this->pipelineService->execute($query);

        return new ServiceResult(
            data: [
                'summary' => $result->summary,
                'by_stage' => $result->byStage,
                'by_department' => $result->byDepartment,
                'by_offer' => $result->byOffer,
                'over_time' => $result->overTime,
                'visualization_hints' => $result->visualizationHints,
            ],
            visualizationHints: $result->visualizationHints,
        );
    }

    private function handleCandidateAnalysis(IntentDTO $intent): ServiceResult
    {
        $params = $intent->parameters;
        $outputFormat = $intent->outputFormat;

        $query = new CandidateQuery(
            jobOfferId: isset($params['job_offer_id']) ? (int) $params['job_offer_id'] : null,
            status: $params['status'] ?? null,
            department: $params['department'] ?? null,
            limit: isset($params['limit']) ? (int) $params['limit'] : 50,
            ids: $params['ids'] ?? [],
            action: $params['action'] ?? null,
        );

        $action = $params['action'] ?? 'search';
        $result = match ($action) {
            'rank' => $this->candidateService->rank($query),
            'compare' => $this->candidateService->compare($query->ids),
            default => $this->candidateService->search($query),
        };

        return new ServiceResult(
            data: [
                'candidates' => $result->candidates,
                'total' => $result->total,
                'ranking' => $result->ranking,
                'comparison' => $result->comparison,
                'visualization_hints' => $result->visualizationHints,
            ],
            visualizationHints: $result->visualizationHints,
            candidates: $result->candidates,
            candidatesAnalyzed: $result->total,
        );
    }

    private function handleDataQuery(IntentDTO $intent): ServiceResult
    {
        $params = $intent->parameters;
        $entity = $params['entity'] ?? $params['type'] ?? 'application';

        return match ($entity) {
            'application', 'candidature', 'candidat' => $this->queryApplications($intent),
            'interview', 'entretien' => $this->queryInterviews($intent),
            'job_offer', 'offer', 'offre' => $this->queryJobOffers($intent),
            default => $this->queryApplications($intent),
        };
    }

    private function queryApplications(IntentDTO $intent): ServiceResult
    {
        $params = $intent->parameters;
        $query = new ApplicationQuery(
            jobOfferId: isset($params['job_offer_id']) ? (int) $params['job_offer_id'] : null,
            status: $params['status'] ?? null,
            department: $params['department'] ?? null,
            limit: isset($params['limit']) ? (int) $params['limit'] : 50,
            ids: $params['ids'] ?? [],
        );

        $applications = $this->applicationService->list($query);

        return new ServiceResult(
            data: ['applications' => $applications, 'total' => count($applications)],
            visualizationHints: ['table'],
            candidates: $applications,
            candidatesAnalyzed: count($applications),
        );
    }

    private function queryInterviews(IntentDTO $intent): ServiceResult
    {
        $params = $intent->parameters;
        $query = new InterviewQuery(
            applicationId: isset($params['application_id']) ? (int) $params['application_id'] : null,
            dateFrom: $params['date_from'] ?? $params['from'] ?? null,
            dateTo: $params['date_to'] ?? $params['to'] ?? null,
            result: $params['result'] ?? null,
        );

        $interviews = $this->interviewService->list($query);
        $stats = $this->interviewService->getStats();

        return new ServiceResult(
            data: [
                'interviews' => $interviews,
                'total' => count($interviews),
                'stats' => $stats,
            ],
            visualizationHints: ['table'],
            interviews: $interviews,
            interviewsPlanned: count($interviews),
        );
    }

    private function queryJobOffers(IntentDTO $intent): ServiceResult
    {
        return new ServiceResult(
            data: ['message' => 'Fonctionnalité de consultation des offres via le service dédié.'],
            visualizationHints: ['table'],
        );
    }

    private function handleMutation(IntentDTO $intent, object $user): ServiceResult
    {
        $params = $intent->parameters;

        $changeset = $this->applicationService->updateStatus(
            applicationId: isset($params['application_id']) ? (int) $params['application_id'] : 0,
            newStatus: $params['new_status'] ?? $params['status'] ?? '',
            user: $user,
        );

        if ($changeset !== null) {
            return new ServiceResult(
                data: ['pending_changeset' => true, 'changeset_id' => $changeset->id],
                pendingChangesets: [$changeset],
                message: "Modification en attente de confirmation.",
            );
        }

        return new ServiceResult(
            data: ['status' => 'applied'],
            message: "Modification effectuée.",
        );
    }

    private function handleScheduling(IntentDTO $intent): ServiceResult
    {
        $params = $intent->parameters;

        if (isset($params['application_id']) && isset($params['date'])) {
            $result = $this->interviewService->schedule(
                applicationId: (int) $params['application_id'],
                scheduledAt: $params['date'],
                notes: $params['notes'] ?? '',
            );

            return new ServiceResult(
                data: ['interview' => $result],
                visualizationHints: ['table'],
                interviewsPlanned: 1,
            );
        }

        $query = new InterviewQuery(
            dateFrom: $params['date_from'] ?? null,
            dateTo: $params['date_to'] ?? null,
        );
        $interviews = $this->interviewService->list($query);

        return new ServiceResult(
            data: ['interviews' => $interviews, 'total' => count($interviews)],
            visualizationHints: ['table'],
            interviews: $interviews,
            interviewsPlanned: count($interviews),
        );
    }

    private function handleReportGeneration(IntentDTO $intent): ServiceResult
    {
        $params = $intent->parameters;
        $type = $params['report_type'] ?? $params['type'] ?? 'pipeline';

        $report = $this->reportService->generate($type, $params);

        return new ServiceResult(
            data: $report,
            visualizationHints: $report['visualization_hints'] ?? ['table'],
        );
    }

    private function generateResponse(IntentDTO $intent, ServiceResult $result): string
    {
        if ($result->message !== null) {
            return $result->message;
        }

        if ($intent->intent === IntentType::GREETING) {
            return $result->message ?? '';
        }

        if ($result->isEmpty()) {
            return 'Aucune donnée trouvée pour votre requête.';
        }

        $prompt = $this->buildGenerationPrompt($intent, $result);

        $request = new ChatRequest(
            messages: [new ChatMessage('user', $prompt)],
            systemPrompt: 'Tu es un assistant RH qui résume des données structurées en français. Sois concis et précis.',
        );

        $response = $this->llmClient->chat($request);

        return $response->content ?: 'Données récupérées avec succès.';
    }

    private function buildGenerationPrompt(IntentDTO $intent, ServiceResult $result): string
    {
        $typeLabel = match ($intent->intent) {
            IntentType::PIPELINE_ANALYSIS => 'analyse de pipeline',
            IntentType::CANDIDATE_ANALYSIS => 'analyse de candidats',
            IntentType::DATA_QUERY => 'consultation de données',
            IntentType::SCHEDULING => 'planification',
            IntentType::REPORT_GENERATION => 'génération de rapport',
            default => 'résultat',
        };

        return "Voici les données d'un {$typeLabel}. Résume-les en 2-3 phrases en français:\n\n"
            . json_encode($result->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function buildAgentResponse(string $message, ServiceResult $result, IntentDTO $intent): AgentResponse
    {
        return new AgentResponse(
            message: $message,
            uiPayload: $result->uiPayload(),
            pendingChangesets: $result->pendingChangesets,
            toolCalls: [],
            plan: $result->planSteps(),
            completedSteps: $result->completedSteps(),
            activeJob: $result->activeJob,
            candidates: $result->candidates,
            interviews: $result->interviews,
            candidatesAnalyzed: $result->candidatesAnalyzed,
            interviewsPlanned: $result->interviewsPlanned,
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

    private function getLastUserMessage(array $messages): string
    {
        for ($i = \count($messages) - 1; $i >= 0; --$i) {
            if ($messages[$i]->role === 'user') {
                return $messages[$i]->content;
            }
        }
        return '';
    }
}
