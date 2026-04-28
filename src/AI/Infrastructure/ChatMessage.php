<?php

declare(strict_types=1);

namespace App\AI\Infrastructure;

final class ChatMessage
{
    public function __construct(
        public readonly string $role,
        public readonly string $content,
    ) {}
}