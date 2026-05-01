<?php

declare(strict_types=1);

namespace App\AI\Infrastructure;

final class AgentResponse
{
    /**
     * @param array<string, mixed> $uiPayload
     * @param array<array<string, mixed>> $pendingChangesets
     * @param array<array<string, mixed>> $toolCalls
     * @param array<string, mixed> $activeJob
     * @param array<array<string, mixed>> $candidates
     * @param array<string, mixed> $plan
     */
    public function __construct(
        public readonly string $message,
        public readonly array $uiPayload = [],
        public readonly array $pendingChangesets = [],
        public readonly array $toolCalls = [],
        public readonly ?array $activeJob = null,
        public readonly array $candidates = [],
        public readonly int $candidatesAnalyzed = 0,
        public readonly int $interviewsPlanned = 0,
        public readonly array $plan = [],
        public readonly int $completedSteps = 0,
    ) {}
}