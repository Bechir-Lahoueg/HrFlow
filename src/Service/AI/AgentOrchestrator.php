<?php

namespace App\Service\AI;

use App\Service\AI\Tool\ToolRegistry;
use App\Service\AI\Tool\ToolValidator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class AgentOrchestrator
{
    private const MAX_ITERATIONS = 5;
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = 7000;
    private const MAX_STORED_MESSAGES = 30;

    public function __construct(
        private LlmClientInterface $llmClient,
        private ToolRegistry $toolRegistry,
        private ToolValidator $toolValidator,
        private LoggerInterface $logger,
        private SessionMemory $sessionMemory,
        private string $projectDir,
        private Security $security
    ) {}

    /** @param array<mixed> $history @return array<mixed> */
    public function chat(string $userInput, array $history = [], ?string $requestId = null, ?string $sessionId = null): array
    {
        $user = $this->security->getUser();
        $userName = ($user !== null && method_exists($user, 'getFirstName')) ? $user->getFirstName() : 'Responsable RH';

        $this->logger->info('AI chat start', [
            'request_id' => $requestId,
            'input' => $userInput,
            'session_id' => $sessionId,
        ]);

        $tools = $this->toolRegistry->getDefinitions();

        // System prompt with explicit tool calling instructions
        $systemPrompt = <<<PROMPT
You are $userName's HR Assistant. Help with recruitment tasks.

CRITICAL RULES:
1. When user asks for DATA (candidates, applications, interviews, jobs, stats): YOU MUST call the appropriate tool. Do NOT describe what you would do - actually call the tool.
2. When user asks for ACTIONS (PDF, reports, move candidate, schedule interview): YOU MUST call the appropriate tool.
3. Greetings only (hi/hello): reply warmly without tools.
4. One tool at a time, wait for results.
5. Never show raw JSON to user - ALWAYS summarize in natural language.
6. Always reply in French.

HOW TO RESPOND AFTER TOOL RESULTS:
- After receiving tool results, summarize the data in 2-3 sentences
- Mention key numbers (counts, totals)
- Highlight important insights
- NEVER output raw JSON or just say "I processed your request"
- Example: "J'ai trouvé 4 offres d'emploi avec un total de 7 candidatures. Les postes les plus demandés sont DevOps Engineer (3 candidats) et Mobile Developer (2 candidats)."

EXAMPLES:
- "show candidates" → call get_candidates → summarize: "J'ai trouvé X candidats: [names]"
- "list my jobs" → call get_job_offers → summarize: "Vous avez X offres actives: [titles]"
- "make a pdf" → call render_pdf → confirm: "PDF généré avec succès"
- "schedule interview" → call schedule_interview → confirm: "Entretien planifié pour [date]"

Available tools: get_candidates, get_applications, get_interviews, get_job_offers, get_applications_per_job_offer, rank_candidates, move_candidate_stage, schedule_interview, get_available_slots, get_pipeline_stats, render_chart, render_pdf, generate_report.

FLOW for CHARTS:
1. Call get_applications_per_job_offer to get candidate counts per job
2. Call render_chart with type='bar', labels=job_titles, values=application_counts
3. NEVER call generate_report for charts - it's for PDF reports only
PROMPT;

        $previous = $this->loadConversationMessages($history, $sessionId);

        $messages = array_merge(
            $previous,
            [['role' => 'user', 'content' => $userInput]]
        );

        $iteration = 0;
        $toolCallsExecuted = [];
        $executedToolNames = []; // Prevent duplicates in plan display
        $activeJob = null;
        $candidatesAnalyzed = 0;
        $interviewsPlanned = 0;
        $validationError = null;
        $applicationsList = [];
        $interviewsList = [];
        $candidatesList = [];

        while ($iteration < self::MAX_ITERATIONS) {
            $iteration++;

            try {
                // Only send tools on first iteration to save tokens
                // After first call, we either have tool results to process or we're done
                $toolsForThisCall = ($iteration === 1) ? $tools : [];

                $response = $this->llmClient->generateContent(
                    $messages,
                    $toolsForThisCall,
                    [
                        'model' => 'llama-3.1-8b-instant',
                        'temperature' => 0.1,
                        'max_tokens' => 1500,
                    ],
                    $systemPrompt
                );

                $parsed = $this->llmClient->parseResponse($response);
                $responseMessage = [
                    'role' => 'assistant',
                    'content' => $parsed['text'],
                ];

                $toolCalls = $parsed['toolCalls'];

                if (empty($toolCalls)) {
                    break;
                }

                // Ensure assistant tool_call ids match the tool result messages
                $normalizedToolCalls = [];
                $responseMessage['tool_calls'] = [];
                foreach ($toolCalls as $tc) {
                    $callId = $tc['id'] ?? ('call_' . uniqid());
                    $normalizedToolCalls[] = [
                        'id' => $callId,
                        'name' => $tc['name'],
                        'args' => $tc['args'],
                    ];
                    $responseMessage['tool_calls'][] = [
                        'id' => $callId,
                        'type' => 'function',
                        'function' => [
                            'name' => $tc['name'],
                            'arguments' => json_encode($tc['args']),
                        ],
                    ];
                }

                $messages[] = $responseMessage;

                foreach ($normalizedToolCalls as $toolCall) {
                    $toolName = $toolCall['name'];
                    $args = $toolCall['args'];

                    try {
                        $this->toolValidator->validate($toolName, $args);
                        $tool = $this->toolRegistry->getTool($toolName);

                        if (!$tool) {
                            throw new \RuntimeException("Tool not found: $toolName");
                        }

                        $result = $tool->execute($args);

                        // Extract UI data for frontend display
                        $this->extractUiData($toolName, $result, $activeJob, $candidatesAnalyzed, $interviewsPlanned, $applicationsList, $interviewsList, $candidatesList);

                        // Truncate large tool results to prevent token limit issues
                        $truncatedContent = $this->truncateToolResult($result);

                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCall['id'],
                            'name' => $toolName,
                            'content' => $truncatedContent,
                        ];

                        if (!in_array($toolName, $executedToolNames)) {
                            $toolCallsExecuted[] = [
                                'tool_name' => $toolName,
                                'status' => 'done',
                                'details' => $result,
                            ];
                            $executedToolNames[] = $toolName;
                        } else {
                            // Update existing tool execution details if needed, 
                            // but keep the plan list unique
                            foreach ($toolCallsExecuted as &$existing) {
                                if ($existing['tool_name'] === $toolName) {
                                    $existing['details'] = $result;
                                }
                            }
                        }

                    } catch (\Exception $e) {
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCall['id'],
                            'name' => $toolName,
                            'content' => json_encode(['error' => $e->getMessage()]),
                        ];

                        $toolCallsExecuted[] = [
                            'tool_name' => $toolName,
                            'status' => 'error',
                            'details' => $e->getMessage(),
                        ];

                        $validationError = $e->getMessage();
                    }
                }

            } catch (\Throwable $e) {
                $errorMsg = $e->getMessage();
                $this->logger->error('AI chat error', [
                    'request_id' => $requestId,
                    'iteration' => $iteration,
                    'error' => $errorMsg,
                ]);

                // Handle rate limit errors with retry
                if (str_contains($errorMsg, 'Rate limit reached') || str_contains($errorMsg, 'rate limit')) {
                    static $retryCount = 0;
                    if ($retryCount < self::MAX_RETRIES) {
                        $retryCount++;
                        $this->logger->info('Rate limit hit, retrying...', ['retry' => $retryCount]);
                        usleep(self::RETRY_DELAY_MS * 1000); // Wait 7 seconds
                        $iteration--; // Don't count this as an iteration
                        continue;
                    }
                }

                return [
                    'message' => "Erreur: " . $errorMsg . "\n\nRequest ID: `{$requestId}`",
                    'plan' => [],
                    'completed_steps' => count($toolCallsExecuted),
                    'tool_calls' => $toolCallsExecuted,
                    'active_job' => $activeJob,
                    'candidates' => [],
                    'candidates_analyzed' => $candidatesAnalyzed,
                    'interviews_planned' => $interviewsPlanned,
                    'validation_error' => $validationError,
                ];
            }
        }

        $finalMessage = $messages[count($messages) - 1]['content'] ?? "Désolé, je n'ai pas pu traiter votre demande.";

        // Clean up the final message - remove raw JSON if present
        $finalMessage = $this->cleanMessage($finalMessage);

        // Detect if user is asking for data/actions that require tools
        $cleanInput = trim(mb_strtolower($userInput));
        $requiresTool = $this->requiresTool($cleanInput);
        $toolsWereCalled = count($toolCallsExecuted) > 0;

        // If tools were expected but not called, show helpful error instead of generic response
        if ($requiresTool && !$toolsWereCalled) {
            $finalMessage = "Je n'ai pas pu exécuter votre demande. Veuillez reformuler en étant plus spécifique.\n\nExemples:\n- 'Montre-moi tous les candidats'\n- 'Liste mes offres d'emploi'\n- 'Génère un PDF des candidats'";
        }

        // Avoid "echo" failure mode on small talk / unknown intent
        $cleanOutput = trim(mb_strtolower((string) $finalMessage));
        
        $greetings = ['hi', 'hello', 'bonjour', 'salut', 'hey', 'coucou'];
        $isGreeting = false;
        foreach ($greetings as $g) {
            if (str_contains($cleanInput, $g) && mb_strlen($cleanInput) < 20) {
                $isGreeting = true;
                break;
            }
        }

        // Handle echo case (AI repeats user input)
        if ($cleanInput === $cleanOutput && mb_strlen($cleanOutput) < 50 && !$isGreeting) {
            $finalMessage = "Bonjour ! Je suis votre assistant recrutement. Je peux vous aider à :\n\n• Lister vos candidats, offres et entretiens\n• Planifier des entretiens\n• Générer des rapports PDF\n• Déplacer des candidats dans le pipeline\n\nQue souhaitez-vous faire ?";
        }

        $this->storeConversationMessages($messages, $sessionId);

        $reportGenerated = false;
        $reportType = null;
        foreach ($toolCallsExecuted as $tc) {
            if (isset($tc['details']['download_url'])) {
                $reportGenerated = true;
                $reportType = $tc['tool_name'] === 'render_pdf' ? 'Rapport Personnalisé' : 'Rapport';
            }
        }

        return [
            'message' => $finalMessage,
            'plan' => array_column($toolCallsExecuted, 'tool_name'),
            'completed_steps' => count($toolCallsExecuted),
            'tool_calls' => $toolCallsExecuted,
            'active_job' => $activeJob,
            'candidates' => [],
            'candidates_analyzed' => $candidatesAnalyzed,
            'applications' => $applicationsList,
            'interviews' => $interviewsList,
            'candidates_list' => $candidatesList,
            'interviews_planned' => $interviewsPlanned,
            'report_generated' => $reportGenerated,
            'report_type' => $reportType,
            'validation_error' => $validationError,
        ];
    }

    /** @param array<mixed>|null $activeJob @param array<mixed> $applicationsList @param array<mixed> $candidatesList @param array<mixed> $interviewsList */
    private function extractUiData(string $toolName, mixed $result, ?array &$activeJob, int &$candidatesAnalyzed, int &$interviewsPlanned, array &$applicationsList, array &$interviewsList, array &$candidatesList): void
    {
        if ($toolName === 'rank_candidates' && is_array($result)) {
            $candidatesAnalyzed = count($result);
        }

        if ($toolName === 'get_job_offers' && is_array($result)) {
            if (!empty($result)) {
                $activeJob = [
                    'title' => $result[0]['title'] ?? 'Job',
                    'location' => $result[0]['location'] ?? '',
                ];
            }
            // Populate candidates_list for UI table display
            foreach ($result as $job) {
                $candidatesList[] = [
                    'id' => $job['id'],
                    'full_name' => $job['title'],
                    'email' => $job['department'] ?? '',
                    'phone' => $job['location'] ?? '',
                    'created_at' => $job['created_at'] ?? ''
                ];
            }
        }

        if ($toolName === 'schedule_interview') {
            $interviewsPlanned++;
        }

        if ($toolName === 'get_applications' && is_array($result) && isset($result['applications'])) {
            foreach ($result['applications'] as $app) {
                $applicationsList[] = [
                    'id' => $app['id'],
                    'candidate_name' => $app['candidate_name'],
                    'email' => $app['email'] ?? '',
                    'job_title' => $app['job_title'] ?? '',
                    'status' => $app['status'],
                    'status_label' => $app['status_label'],
                    'applied_at' => $app['applied_at']
                ];
            }
        }

        if ($toolName === 'get_interviews' && is_array($result) && isset($result['interviews'])) {
            foreach ($result['interviews'] as $intv) {
                $interviewsList[] = [
                    'id' => $intv['id'],
                    'candidate_name' => $intv['candidate_name'],
                    'job_title' => $intv['job_title'] ?? '',
                    'type' => $intv['type'],
                    'interview_date' => $intv['interview_date'],
                    'result' => $intv['result'],
                    'score' => $intv['score']
                ];
            }
        }

        if ($toolName === 'get_candidates' && is_array($result) && isset($result['candidates'])) {
            foreach ($result['candidates'] as $cand) {
                $candidatesList[] = [
                    'id' => $cand['id'],
                    'full_name' => $cand['full_name'],
                    'email' => $cand['email'],
                    'phone' => $cand['phone'] ?? '',
                    'created_at' => $cand['created_at']
                ];
            }
        }
    }

    /** @phpstan-ignore method.unused */
    private function loadSkills(): string
    {
        $skillsDir = $this->projectDir . '/config/ai/skills';
        $content = '';

        if (is_dir($skillsDir)) {
            $files = glob($skillsDir . '/*.md');
            if ($files !== false) {
                foreach ($files as $file) {
                    $content .= file_get_contents($file) . "\n\n";
                }
            }
        }

        return $content;
    }

    /** @param array<mixed> $history @return array<mixed> */
    private function loadConversationMessages(array $history, ?string $sessionId): array
    {
        // Prefer server-side session memory to keep the UI simple.
        if ($sessionId) {
            $stored = $this->sessionMemory->get('chat:' . $sessionId);
            if (is_array($stored) && !empty($stored)) {
                return array_slice($stored, -self::MAX_STORED_MESSAGES);
            }
        }

        // Fallback: accept client-provided history in a minimal shape.
        $normalized = [];
        foreach ($history as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $role = $msg['role'] ?? null;
            $content = $msg['content'] ?? null;
            if (!is_string($role) || !is_string($content)) {
                continue;
            }
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $normalized[] = ['role' => $role, 'content' => $content];
        }

        return array_slice($normalized, -self::MAX_STORED_MESSAGES);
    }

    /** @param array<mixed> $messages */
    private function storeConversationMessages(array $messages, ?string $sessionId): void
    {
        if (!$sessionId) {
            return;
        }

        // Store only user/assistant/tool messages (exclude system prompt).
        $toStore = [];
        foreach ($messages as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $role = $msg['role'] ?? null;
            if (!is_string($role) || $role === 'system') {
                continue;
            }

            // Keep only the fields needed for the next call.
            $storedMsg = ['role' => $role, 'content' => $msg['content'] ?? ''];
            if ($role === 'assistant' && isset($msg['tool_calls'])) {
                $storedMsg['tool_calls'] = $msg['tool_calls'];
            }
            if ($role === 'tool') {
                if (isset($msg['tool_call_id'])) {
                    $storedMsg['tool_call_id'] = $msg['tool_call_id'];
                }
                if (isset($msg['name'])) {
                    $storedMsg['name'] = $msg['name'];
                }
            }
            $toStore[] = $storedMsg;
        }

        $this->sessionMemory->store('chat:' . $sessionId, array_slice($toStore, -self::MAX_STORED_MESSAGES));
    }

    private function cleanMessage(string $message): string
    {
        $trimmed = trim($message);

        // Check if message is just JSON (starts with { or [ and ends with } or ])
        $looksLikeJson = (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) ||
                         (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'));

        if ($looksLikeJson) {
            // Try to decode it
            $decoded = json_decode($trimmed, true);
            if ($decoded !== null) {
                // It's valid JSON - replace with generic message
                return "J'ai trouvé les informations demandées. Les détails sont affichés ci-dessous. Comment puis-je vous aider d'autre ?";
            }
        }

        return $trimmed;
    }

    /**
     * Truncate large tool results to prevent token limit issues.
     * Returns a NATURAL LANGUAGE summary for the LLM while full data goes to UI via extractUiData.
     */
    private function truncateToolResult(mixed $result): string
    {
        if (!is_array($result)) {
            return is_string($result) ? $result : (string) json_encode($result);
        }

        // Build natural language summary instead of JSON
        // This helps the LLM understand what to say without echoing JSON back

        // For candidates list
        if (isset($result['candidates']) && is_array($result['candidates'])) {
            $count = count($result['candidates']);
            $names = array_slice(array_column($result['candidates'], 'full_name'), 0, 5);
            return "Tool result: Found {$count} candidates. " .
                   "Names: " . implode(', ', $names) . ". " .
                   "The full candidate list with all details is displayed in the UI table below.";
        }

        // For interviews list
        if (isset($result['interviews']) && is_array($result['interviews'])) {
            $count = count($result['interviews']);
            return "Tool result: Found {$count} interviews scheduled. " .
                   "The full interview schedule is displayed in the UI table below.";
        }

        // For applications list
        if (isset($result['applications']) && is_array($result['applications'])) {
            $count = count($result['applications']);
            return "Tool result: Found {$count} applications. " .
                   "The full application list is displayed in the UI table below.";
        }

        // For job offers (direct array)
        if (isset($result[0]) && isset($result[0]['title'])) {
            $count = count($result);
            $titles = array_slice(array_column($result, 'title'), 0, 5);
            return "Tool result: Found {$count} job offers. " .
                   "Titles: " . implode(', ', $titles) . ". " .
                   "The full job list is displayed in the UI table below.";
        }

        // For PDF generation
        if (isset($result['download_url'])) {
            return "Tool result: PDF generated successfully. Download URL available.";
        }

        // For chart generation
        if (isset($result['chart_data'])) {
            return "Tool result: Chart generated successfully with data visualization.";
        }

        // Default: return natural language description
        $encoded = json_encode($result);
        if ($encoded === false || strlen($encoded) > 2000) {
            return "Tool result: Data retrieved successfully. The complete results are displayed in the UI below.";
        }

        return $encoded;
    }

    private function requiresTool(string $cleanInput): bool
    {
        $keywords = ['candidat', 'application', 'offre', 'interview', 'entretien', 'pdf', 'rapport', 'statistique', 'liste', 'montre', 'génère', 'planifie', 'déplace', 'envoie'];
        foreach ($keywords as $keyword) {
            if (str_contains($cleanInput, $keyword)) {
                return true;
            }
        }
        return false;
    }
}