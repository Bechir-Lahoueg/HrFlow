<?php

namespace App\Service\AI\Tool;

/**
 * Interface for all agentic tools.
 */
interface ToolInterface
{
    /**
     * Returns the name of the tool (must be snake_case for Gemini).
     */
    public function getName(): string;

    /**
     * Returns the Gemini function declaration (JSON schema).
     * @return array<mixed>
     */
    public function getDefinition(): array;

    /**
     * Executes the tool logic.
     * 
     * @param array<mixed> $args Arguments provided by Gemini
     * @return mixed The result of the execution
     */
    public function execute(array $args): mixed;
}
