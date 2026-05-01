<?php

declare(strict_types=1);

namespace App\AI\Infrastructure;

final class ChatRequest
{
    /**
     * @param ChatMessage[] $messages
     * @param array<int, array<string, mixed>> $tools
     */
    public function __construct(
        public readonly array $messages,
        public readonly string $systemPrompt = '',
        public readonly array $tools = [],
        public readonly int $maxTools = 5,
    ) {}
}