<?php

declare(strict_types=1);

namespace App\AI\Infrastructure;

final class ConversationContext
{
    /**
     * @param ChatMessage[] $messages
     */
    public function __construct(
        public readonly array $messages,
        public readonly object $user,
        public readonly string $sessionId,
        public readonly ?string $activeIntent = null,
    ) {}
}