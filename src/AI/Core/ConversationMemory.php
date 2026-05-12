<?php

declare(strict_types=1);

namespace App\AI\Core;

use Psr\Cache\CacheItemPoolInterface;

final class ConversationMemory
{
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {}

    public function load(string $sessionId): ?string
    {
        try {
            $item = $this->cache->getItem($this->getCacheKey($sessionId));
            if (!$item->isHit()) {
                return null;
            }
            $data = $item->get();
            return \is_string($data) ? $data : null;
        } catch (\Exception) {
            return null;
        }
    }

    public function save(string $sessionId, string $lastIntent, array $lastParameters = []): void
    {
        try {
            $item = $this->cache->getItem($this->getCacheKey($sessionId));
            $item->set(\json_encode([
                'lastIntent' => $lastIntent,
                'lastParameters' => $lastParameters,
                'updatedAt' => \date('c'),
            ]));
            $item->expiresAfter(self::CACHE_TTL);
            $this->cache->save($item);
        } catch (\Exception) {
        }
    }

    public function clear(string $sessionId): void
    {
        try {
            $this->cache->deleteItem($this->getCacheKey($sessionId));
        } catch (\Exception) {
        }
    }

    private function getCacheKey(string $sessionId): string
    {
        return "chat_context_{$sessionId}";
    }
}
