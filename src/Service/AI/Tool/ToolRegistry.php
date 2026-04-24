<?php

namespace App\Service\AI\Tool;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class ToolRegistry
{
    /** @var ToolInterface[] */
    private array $tools = [];

    public function __construct(
        #[TaggedIterator('app.ai_tool')] iterable $tools
    ) {
        foreach ($tools as $tool) {
            $this->tools[$tool->getName()] = $tool;
        }
    }

    /**
     * @return ToolInterface[]
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    public function getTool(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Returns all tool definitions in Gemini format.
     */
    public function getDefinitions(): array
    {
        $definitions = [];
        foreach ($this->tools as $tool) {
            $definitions[] = $tool->getDefinition();
        }
        return $definitions;
    }
}
