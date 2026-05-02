<?php

declare(strict_types=1);

namespace App\Tests\AI;

use App\AI\Contract\ChangesetStorageInterface;
use App\AI\Domain\Enum\ChangesetStatus;
use App\AI\Domain\ValueObject\PendingChangeset;
use App\AI\Infrastructure\ToolCall;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

class ChangesetManagerTest extends TestCase
{
    private \App\AI\Core\ChangesetManager $manager;

    protected function setUp(): void
    {
        $storage = $this->createMock(ChangesetStorageInterface::class);
        $this->manager = new \App\AI\Core\ChangesetManager($storage);
    }

    public function testStageCreatesPendingChangeset(): void
    {
        $toolCall = new ToolCall('1', 'test_tool', ['key' => 'value']);
        $result = ['sessionId' => 'test-session'];
        $user = new class {
            public function getId(): int {
                return 1;
            }
        };

        $changeset = $this->manager->stage($toolCall, $result, $user);

        $this->assertNotEmpty($changeset->id);
        $this->assertSame('test_tool', $changeset->tool);
        $this->assertSame(ChangesetStatus::PENDING, $changeset->status);
    }

    public function testGetPendingFiltersOnlyPending(): void
    {
        $user = new class {};

        $pending = $this->manager->getPending('test-session');

        $this->assertIsArray($pending);
    }

    public function testConfirmThrowsOnNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $user = new class {
            public function getId(): int {
                return 1;
            }
        };

        $this->manager->confirm('nonexistent', $user);
    }

    public function testRevertThrowsOnNonPending(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->revert('nonexistent');
    }
}