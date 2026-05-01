<?php

declare(strict_types=1);

namespace App\AI\Infrastructure;

final class ToolDefinition
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters = [],
    ) {}
}