<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\Contract\ToolInterface;
use App\AI\Domain\ValueObject\ToolOutput;

abstract class ApplicationTool implements ToolInterface
{
    abstract protected function getToolName(): string;

    abstract protected function getToolDescription(): string;

    public function getName(): string
    {
        return $this->getToolName();
    }

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

    protected function getParameters(): array
    {
        return [];
    }

    protected function getRequired(): array
    {
        return [];
    }

    protected function createOutput(string $summary, array $uiPayload = [], bool $pendingChange = false, ?object $changeset = null): ToolOutput
    {
        return new ToolOutput(
            llmSummary: $summary,
            uiPayload: $uiPayload,
            hasPendingChange: $pendingChange,
            pendingChangeset: $changeset,
        );
    }
}