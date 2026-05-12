<?php

declare(strict_types=1);

namespace App\AI\Infrastructure;

use App\AI\Contract\ToolInterface;

final class ChatRequest
{
    /**
     * @param ChatMessage[] $messages
     * @param ToolInterface[]|array<int, array<string, mixed>> $tools
     */
    public function __construct(
        public readonly array $messages,
        public readonly string $systemPrompt = '',
        public readonly array $tools = [],
        public readonly int $maxTools = 5,
        public readonly ?string $responseMimeType = null,
    ) {}
}