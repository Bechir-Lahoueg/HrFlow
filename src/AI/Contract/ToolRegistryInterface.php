<?php

declare(strict_types=1);

namespace App\AI\Contract;

interface ToolRegistryInterface
{
    public function get(string $name): ToolInterface;

    /**
     * @param string[] $names
     * @return ToolInterface[]
     */
    public function getSubset(array $names): array;

    /**
     * @return ToolInterface[]
     */
    public function all(): array;
}