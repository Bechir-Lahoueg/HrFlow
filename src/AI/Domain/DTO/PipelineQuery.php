<?php

declare(strict_types=1);

namespace App\AI\Domain\DTO;

final class PipelineQuery
{
    public function __construct(
        public readonly ?string $department = null,
        public readonly ?string $dateRange = null,
        public readonly ?string $groupBy = null,
        public readonly array $metrics = [],
        public readonly ?string $status = null,
        public readonly ?int $jobOfferId = null,
    ) {}
}
