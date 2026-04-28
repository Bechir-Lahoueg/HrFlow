<?php

declare(strict_types=1);

namespace App\AI\Domain\ValueObject;

use App\AI\Domain\Enum\ChangesetStatus;

final class ToolOutput
{
    public function __construct(
        public readonly string $llmSummary,
        public readonly array $uiPayload = [],
        public readonly bool $hasPendingChange = false,
        public readonly ?PendingChangeset $pendingChangeset = null,
    ) {}
}