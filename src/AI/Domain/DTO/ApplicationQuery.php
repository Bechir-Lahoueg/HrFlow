<?php

declare(strict_types=1);

namespace App\AI\Domain\DTO;

final class ApplicationQuery
{
    public function __construct(
        public readonly ?int $jobOfferId = null,
        public readonly ?string $status = null,
        public readonly ?string $department = null,
        public readonly ?int $limit = 50,
        public readonly array $ids = [],
        public readonly ?string $action = null,
        public readonly ?string $newStatus = null,
    ) {}
}
