<?php

declare(strict_types=1);

namespace App\Tests\AI;

use App\AI\Core\AgentOrchestrator;
use App\AI\Contract\LlmClientInterface;
use App\AI\Contract\ToolRegistryInterface;
use App\AI\Core\ToolValidator;
use App\AI\Core\ConversationMemory;
use App\AI\Core\RecruitmentContextProvider;
use App\AI\Core\IntentRouter;
use App\AI\Core\ChangesetManager;
use App\AI\Infrastructure\ConversationContext;
use App\AI\Infrastructure\ChatMessage;
use PHPUnit\Framework\TestCase;

class AgentOrchestratorTest extends TestCase
{
    private AgentOrchestrator $orchestrator;

    protected function setUp(): void
    {
        $llmClient = $this->createMock(LlmClientInterface::class);
        $toolRegistry = $this->createMock(ToolRegistryInterface::class);
        $toolValidator = new ToolValidator();
        $memory = $this->createMock(ConversationMemory::class);
        $contextProvider = new RecruitmentContextProvider();
        $intentRouter = $this->createMock(IntentRouter::class);
        $changesetManager = $this->createMock(ChangesetManager::class);

        $this->orchestrator = new AgentOrchestrator(
            $llmClient,
            $toolRegistry,
            $toolValidator,
            $memory,
            $contextProvider,
            $intentRouter,
            $changesetManager,
        );
    }

    public function testProcessHandlesGreeting(): void
    {
        $user = new class {
            public function getFirstName(): string {
                return 'Test';
            }
        };

        $context = new ConversationContext(
            messages: [new ChatMessage('user', 'Bonjour')],
            user: $user,
            sessionId: 'test-session',
        );

        $result = $this->orchestrator->process($context);

        $this->assertNotEmpty($result->message);
        $this->assertStringContainsString('Bonjour', $result->message);
    }

    public function testProcessReturnsUiPayload(): void
    {
        $user = new class {};

        $context = new ConversationContext(
            messages: [new ChatMessage('user', 'Montre-moi les candidats')],
            user: $user,
            sessionId: 'test-session',
        );

        $result = $this->orchestrator->process($context);

        $this->assertIsArray($result->uiPayload);
    }

    public function testProcessIncludesPendingChangesets(): void
    {
        $user = new class {};

        $context = new ConversationContext(
            messages: [new ChatMessage('user', 'Modifie le statut')],
            user: $user,
            sessionId: 'test-session',
        );

        $result = $this->orchestrator->process($context);

        $this->assertIsArray($result->pendingChangesets);
    }
}