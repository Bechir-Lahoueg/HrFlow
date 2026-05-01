<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Contract\ChangesetStorageInterface;
use App\AI\Domain\Enum\ChangesetStatus;
use App\AI\Domain\ValueObject\PendingChangeset;
use App\AI\Infrastructure\ToolCall;
use DateTimeImmutable;

final class ChangesetManager
{
    public function __construct(
        private readonly ChangesetStorageInterface $storage,
    ) {}

    public function stage(ToolCall $toolCall, array $result, object $user): PendingChangeset
    {
        $changeset = PendingChangeset::create(
            id: bin2hex(random_bytes(8)),
            sessionId: $result['sessionId'] ?? 'default',
            tool: $toolCall->name,
            action: $toolCall->arguments['action'] ?? 'update',
            payload: [
                'tool_args' => $toolCall->arguments,
                'result' => $result,
                'user_id' => method_exists($user, 'getId') ? $user->getId() : null,
            ],
        );

        $this->storage->persist($changeset);

        return $changeset;
    }

    public function confirm(string $changesetId, object $user): void
    {
        $changeset = $this->storage->find($changesetId);

        if ($changeset === null) {
            throw new \InvalidArgumentException("Changeset not found: {$changesetId}");
        }

        if ($changeset->status !== ChangesetStatus::PENDING) {
            throw new \InvalidArgumentException("Changeset is not pending");
        }

        $confirmed = $changeset->confirm();
        $this->storage->persist($confirmed);

        $this->applyChange($confirmed, $user);
    }

    public function revert(string $changesetId): void
    {
        $changeset = $this->storage->find($changesetId);

        if ($changeset === null) {
            throw new \InvalidArgumentException("Changeset not found: {$changesetId}");
        }

        if ($changeset->status !== ChangesetStatus::PENDING) {
            throw new \InvalidArgumentException("Cannot revert non-pending changeset");
        }

        $reverted = $changeset->revert();
        $this->storage->persist($reverted);
    }

    /**
     * @return PendingChangeset[]
     */
    public function getPending(string $sessionId): array
    {
        return \array_filter(
            $this->storage->findBySession($sessionId),
            fn(PendingChangeset $c) => $c->status === ChangesetStatus::PENDING,
        );
    }

    private function applyChange(PendingChangeset $changeset, object $user): void
    {
    }
}