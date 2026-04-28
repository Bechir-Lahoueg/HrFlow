<?php

declare(strict_types=1);

namespace App\AI\Infrastructure;

final class ChatResponse
{
    /**
     * @param ToolCall[] $toolCalls
     */
    public function __construct(
        public readonly string $content,
        public readonly array $toolCalls = [],
    ) {}
}