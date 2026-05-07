declare(strict_types=1);

namespace App\Tests\AI\Stubs;

use App\AI\Contract\ToolInterface;
use App\AI\Contract\ToolDefinition;
use App\AI\Contract\ToolOutput;
use App\AI\Contract\User;

final class StubTool implements ToolInterface
{
    public function getName(): string
    {
        return 'stub_tool';
    }

    public function getDefinition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->getName(),
            description: 'Stub tool for testing',
        );
    }

    public function execute(array $arguments, User $user): ToolOutput
    {
        return new ToolOutput(llmSummary: 'Tool executed');
    }
}

final class StubToolRegistry
{
    public function getSubset(array $names): array
    {
        return [new StubTool()];
    }
}

final class InMemoryToolRegistry
{
    public function getSubset(array $names): array
    {
        return [];
    }
}

final class InMemoryToolValidator
{
    public function validate(...$args): void
    {
    }
}

final class InMemoryRecruitmentContextProvider
{
    public function buildSystemPrompt(...$args): string
    {
        return 'System prompt';
    }
}

final class InMemoryIntentRouter
{
    public function classify(...$args)
    {
        return \App\AI\Domain\Enum\IntentType::GREETING;
    }

    public function getSystemInstruction(...$args): string
    {
        return 'System instruction';
    }
}

final class InMemoryConversationMemory
{
    public function load(...$args): array
    {
        return [];
    }

    public function save(...$args): void
    {
    }

    public function add(...$args): void
    {
    }

    public function getMessages(): array
    {
        return [];
    }
}

final class InMemoryChangesetStorage implements \App\AI\Contract\ChangesetStorageInterface
{
    private array $changesets = [];

    public function persist(\App\AI\Domain\ValueObject\PendingChangeset $changeset): void
    {
        $this->changesets[$changeset->id] = $changeset;
    }

    public function find(string $id): ?\App\AI\Domain\ValueObject\PendingChangeset
    {
        return $this->changesets[$id] ?? null;
    }

    public function findBySession(string $sessionId): array
    {
        return array_values(array_filter($this->changesets, fn($cs) => $cs->sessionId === $sessionId));
    }
}

final class InMemoryChangesetManager
{
    private InMemoryChangesetStorage $storage;

    public function __construct()
    {
        $this->storage = new InMemoryChangesetStorage();
    }

    public function stage(\App\AI\Contract\ToolCall $toolCall, array $result, User $user): \App\AI\Domain\ValueObject\PendingChangeset
    {
        return \App\AI\Domain\ValueObject\PendingChangeset::create(
            id: 'test-id-' . uniqid(),
            sessionId: 'session_' . $user->id,
            tool: $toolCall->name,
            action: $toolCall->arguments['action'] ?? 'execute',
            payload: ['toolCall' => $toolCall->toArray(), 'result' => $result],
        );
    }

    public function confirm(string $changesetId, User $user): \App\AI\Domain\ValueObject\PendingChangeset
    {
        return $this->storage->find($changesetId)?->confirm() ?? throw new \RuntimeException('Not found');
    }

    public function revert(string $changesetId): \App\AI\Domain\ValueObject\PendingChangeset
    {
        return $this->storage->find($changesetId)?->revert() ?? throw new \RuntimeException('Not found');
    }

    public function getPending(string $sessionId): array
    {
        return $this->storage->findBySession($sessionId);
    }
}