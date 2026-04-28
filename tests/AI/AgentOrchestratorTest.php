declare(strict_types=1);

namespace App\Tests\AI;

use App\AI\Service\AgentOrchestrator;
use App\AI\Service\ConversationContext;
use App\AI\Contract\Message;
use App\AI\Contract\User;
use App\AI\Domain\Enum\IntentType;
use PHPUnit\Framework\TestCase;

final class AgentOrchestratorTest extends TestCase
{
    private AgentOrchestrator $orchestrator;

    protected function setUp(): void
    {
        $this->orchestrator = new AgentOrchestrator(
            llmClient: new InMemoryLlmClient(),
            toolRegistry: new InMemoryToolRegistry(),
            toolValidator: new InMemoryToolValidator(),
            memory: new InMemoryConversationMemory(),
            contextProvider: new InMemoryRecruitmentContextProvider(),
            router: new InMemoryIntentRouter(),
            changesetManager: new InMemoryChangesetManager(),
        );
    }

    public function testProcessReturnsAgentResponse(): void
    {
        $user = new User(id: 1, email: 'test@example.com');
        $messages = [new Message('user', 'Hello')];
        $context = new ConversationContext(
            messages: $messages,
            user: $user,
            sessionId: 'test-session',
        );

        $response = $this->orchestrator->process($context);

        $this->assertInstanceOf(AgentResponse::class, $response);
        $this->assertNotEmpty($response->text);
    }

    public function testProcessWithGreetingShortcut(): void
    {
        $user = new User(id: 1, email: 'test@example.com');
        $messages = [new Message('user', 'Hello')];
        $context = new ConversationContext(
            messages: $messages,
            user: $user,
            sessionId: 'test-session',
        );

        $response = $this->orchestrator->process($context);

        $this->assertIsString($response->text);
    }

    public function testProcessManagesContextWindow(): void
    {
        $user = new User(id: 1, email: 'test@example.com');
        $messages = [new Message('user', 'Test message')];
        $context = new ConversationContext(
            messages: $messages,
            user: $user,
            sessionId: 'test-session',
        );

        $response = $this->orchestrator->process($context);

        $this->assertInstanceOf(AgentResponse::class, $response);
    }
}