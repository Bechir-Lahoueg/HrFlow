<?php

namespace App\Service\AI;

use Symfony\Component\HttpFoundation\RequestStack;

class SessionMemory
{
    private const SESSION_KEY = 'ai_agent_memory';

    public function __construct(
        private RequestStack $requestStack
    ) {}

    public function store(string $key, mixed $value): void
    {
        $session = $this->requestStack->getSession();
        $memory = $session->get(self::SESSION_KEY, []);
        $memory[$key] = $value;
        $session->set(self::SESSION_KEY, $memory);
    }

    public function get(string $key): mixed
    {
        $session = $this->requestStack->getSession();
        $memory = $session->get(self::SESSION_KEY, []);
        return $memory[$key] ?? null;
    }

    public function getAll(): array
    {
        $session = $this->requestStack->getSession();
        return $session->get(self::SESSION_KEY, []);
    }

    public function clear(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }

    public function delete(string $key): void
    {
        $session = $this->requestStack->getSession();
        $memory = $session->get(self::SESSION_KEY, []);
        unset($memory[$key]);
        $session->set(self::SESSION_KEY, $memory);
    }
}
