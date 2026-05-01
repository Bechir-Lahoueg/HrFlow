<?php

declare(strict_types=1);

namespace App\AI\Contract;

use App\AI\Domain\ValueObject\ToolOutput;

interface ToolInterface
{
    public function getName(): string;

    public function getDefinition(): array;

    public function execute(array $args, object $user): ToolOutput;
}