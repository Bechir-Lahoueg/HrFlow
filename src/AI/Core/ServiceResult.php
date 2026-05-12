<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Domain\ValueObject\PendingChangeset;

final class ServiceResult
{
    public readonly ?array $activeJob;

    /**
     * @param array<string, mixed> $data
     * @param PendingChangeset[] $pendingChangesets
     * @param array<string, mixed>[] $candidates
     * @param array<string, mixed>[] $interviews
     */
    public function __construct(
        public readonly array $data = [],
        public readonly array $visualizationHints = [],
        public readonly array $pendingChangesets = [],
        public readonly ?string $message = null,
        public readonly array $candidates = [],
        public readonly array $interviews = [],
        public readonly int $candidatesAnalyzed = 0,
        public readonly int $interviewsPlanned = 0,
    ) {
        $this->activeJob = $this->extractActiveJob();
    }

    public function isEmpty(): bool
    {
        return empty($this->data) && $this->message === null;
    }

    public function uiPayload(): array
    {
        $payload = [
            'type' => 'result',
            'data' => $this->data,
            'visualization_hints' => $this->visualizationHints,
        ];

        if (!empty($this->candidates)) {
            $payload['candidates'] = $this->candidates;
        }
        if (!empty($this->interviews)) {
            $payload['interviews'] = $this->interviews;
        }

        return $payload;
    }

    public function planSteps(): array
    {
        return ['Traitement de la requête'];
    }

    public function completedSteps(): int
    {
        return 1;
    }

    private function extractActiveJob(): ?array
    {
        if (isset($this->data['by_offer']) && is_array($this->data['by_offer'])) {
            $offers = $this->data['by_offer'];
            if (!empty($offers)) {
                $titles = array_keys($offers);
                return ['title' => $titles[0], 'applications' => $offers[$titles[0]]];
            }
        }
        if (isset($this->data['applications']) && is_array($this->data['applications'])) {
            $apps = $this->data['applications'];
            if (!empty($apps) && isset($apps[0]['job_title'])) {
                return ['title' => $apps[0]['job_title']];
            }
        }
        return null;
    }
}
