<?php

declare(strict_types=1);

namespace App\AI\Domain\DTO;

final class CandidateResult
{
    public function __construct(
        public readonly array $candidates = [],
        public readonly int $total = 0,
        public readonly ?array $ranking = null,
        public readonly ?array $comparison = null,
        public readonly array $visualizationHints = [],
    ) {}
}
