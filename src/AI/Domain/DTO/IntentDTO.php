<?php

declare(strict_types=1);

namespace App\AI\Domain\DTO;

use App\AI\Domain\Enum\IntentType;

final class IntentDTO
{
    public function __construct(
        public readonly IntentType $intent,
        public readonly array $parameters = [],
        public readonly array $outputFormat = [],
        public readonly ?string $originalMessage = null,
    ) {}
}
