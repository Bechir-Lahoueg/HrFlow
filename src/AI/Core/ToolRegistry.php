<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Contract\ToolInterface;
use App\AI\Contract\ToolRegistryInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class ToolRegistry implements ToolRegistryInterface
{
    /**
     * @param iterable<ToolInterface> $tools
     */
    public function __construct(
        #[TaggedIterator('app.ai_tool')]
        private readonly iterable $tools = [],
    ) {}

    public function get(string $name): ToolInterface
    {
        foreach ($this->tools as $tool) {
            if ($tool->getName() === $name) {
                return $tool;
            }
        }

        throw new \InvalidArgumentException("Tool not found: {$name}");
    }

    /**
     * @param string[] $names
     * @return ToolInterface[]
     */
    public function getSubset(array $names): array
    {
        $subset = [];
        foreach ($names as $name) {
            try {
                $subset[] = $this->get($name);
            } catch (\InvalidArgumentException) {
            }
        }
        return $subset;
    }

    public function all(): array
    {
        return $this->tools;
    }
}