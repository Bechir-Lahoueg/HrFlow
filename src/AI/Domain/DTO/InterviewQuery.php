<?php

declare(strict_types=1);

namespace App\AI\Domain\DTO;

final class InterviewQuery
{
    public function __construct(
        public readonly ?int $applicationId = null,
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
        public readonly ?string $result = null,
        public readonly ?string $action = null,
    ) {}
}
