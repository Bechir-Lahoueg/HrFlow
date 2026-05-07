<?php

declare(strict_types=1);

namespace App\AI\Contract;

use App\AI\Domain\ValueObject\ToolOutput;

interface ToolInterface
{
    public function getName(): string;

    /**
     * @return array<string, mixed>
     */
    public function getDefinition(): array;

    /**
     * @param array<string, mixed> $args
     */
    public function execute(array $args, object $user): ToolOutput;
}