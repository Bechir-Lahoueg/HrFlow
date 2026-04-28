<?php

declare(strict_types=1);

namespace App\AI\Domain\ValueObject;

use App\AI\Domain\Enum\ChangesetStatus;
use DateTimeImmutable;

final class PendingChangeset
{
    public function __construct(
        public readonly string $id,
        public readonly string $sessionId,
        public readonly string $tool,
        public readonly string $action,
        public readonly array $payload,
        public readonly ChangesetStatus $status,
        public readonly DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $confirmedAt = null,
    ) {}

    public static function create(
        string $id,
        string $sessionId,
        string $tool,
        string $action,
        array $payload,
    ): self {
        return new self(
            id: $id,
            sessionId: $sessionId,
            tool: $tool,
            action: $action,
            payload: $payload,
            status: ChangesetStatus::PENDING,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function confirm(): self
    {
        return new self(
            id: $this->id,
            sessionId: $this->sessionId,
            tool: $this->tool,
            action: $this->action,
            payload: $this->payload,
            status: ChangesetStatus::CONFIRMED,
            createdAt: $this->createdAt,
            confirmedAt: new DateTimeImmutable(),
        );
    }

    public function revert(): self
    {
        return new self(
            id: $this->id,
            sessionId: $this->sessionId,
            tool: $this->tool,
            action: $this->action,
            payload: $this->payload,
            status: ChangesetStatus::REVERTED,
            createdAt: $this->createdAt,
            confirmedAt: $this->confirmedAt,
        );
    }

    public function expire(): self
    {
        return new self(
            id: $this->id,
            sessionId: $this->sessionId,
            tool: $this->tool,
            action: $this->action,
            payload: $this->payload,
            status: ChangesetStatus::EXPIRED,
            createdAt: $this->createdAt,
            confirmedAt: $this->confirmedAt,
        );
    }
}