<?php

declare(strict_types=1);

namespace App\AI\Domain\DTO;

final class PipelineResult
{
    public function __construct(
        public readonly array $summary = [],
        public readonly array $byStage = [],
        public readonly array $byDepartment = [],
        public readonly array $byOffer = [],
        public readonly array $overTime = [],
        public readonly array $visualizationHints = [],
    ) {}
}
