<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Infrastructure\ChatMessage;
use Psr\Cache\CacheItemPoolInterface;

final class ConversationMemory
{
    private const MAX_MESSAGES = 20;
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {}

    /**
     * @return ChatMessage[]
     */
    public function load(string $sessionId): array
    {
        $key = $this->getCacheKey($sessionId);

        try {
            $item = $this->cache->getItem($key);
            if (!$item->isHit()) {
                return [];
            }

            $data = $item->get();
            if (!\is_array($data)) {
                return [];
            }

            return array_map(
                fn(array $item) => new ChatMessage($item['role'], $item['content']),
                $data,
            );
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * @param ChatMessage[] $messages
     */
    public function save(string $sessionId, array $messages): void
    {
        $trimmed = \array_slice($messages, -self::MAX_MESSAGES);
        $data = array_map(
            fn(ChatMessage $msg) => ['role' => $msg->role, 'content' => $msg->content],
            $trimmed,
        );

        try {
            $item = $this->cache->getItem($this->getCacheKey($sessionId));
            $item->set($data);
            $item->expiresAfter(self::CACHE_TTL);
            $this->cache->save($item);
        } catch (\Exception) {
        }
    }

    /**
     * @param ChatMessage[] $messages
     */
    public function summarizeOld(array $messages): ChatMessage
    {
        $count = \count($messages);
        $summary = "Previous {$count} messages summarized.";

        return new ChatMessage('system', $summary);
    }

    private function getCacheKey(string $sessionId): string
    {
        return "chat_memory_{$sessionId}";
    }
}