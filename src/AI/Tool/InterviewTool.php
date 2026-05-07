<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\Contract\ToolInterface;
use App\AI\Domain\ValueObject\ToolOutput;

abstract class InterviewTool implements ToolInterface
{
    abstract protected function getToolName(): string;

    abstract protected function getToolDescription(): string;

    public function getName(): string
    {
        return $this->getToolName();
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinition(): array
    {
        return [
            'name' => $this->getName(),
            'description' => $this->getToolDescription(),
            'parameters' => [
                'type' => 'object',
                'properties' => $this->getParameters(),
                'required' => $this->getRequired(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getParameters(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    protected function getRequired(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $uiPayload
     */
    protected function createOutput(string $summary, array $uiPayload = [], bool $pendingChange = false, ?\App\AI\Domain\ValueObject\PendingChangeset $changeset = null): ToolOutput
    {
        return new ToolOutput(
            llmSummary: $summary,
            uiPayload: $uiPayload,
            hasPendingChange: $pendingChange,
            pendingChangeset: $changeset,
        );
    }
}
