<?php

declare(strict_types=1);

namespace App\AI\Infrastructure;

final class ChatMessage
{
    public function __construct(
        public readonly string $role,
        public readonly string $content,
        public readonly ?string $toolCallId = null,
        public readonly ?string $toolCallName = null,
        public readonly ?array $toolCallArgs = null,
        public readonly ?array $toolResponse = null,
    ) {}

    public function isToolCall(): bool
    {
        return $this->toolCallId !== null;
    }

    public function isToolResponse(): bool
    {
        return $this->toolResponse !== null;
    }
}