<?php

namespace App\Service\AI;

interface LlmClientInterface
{
    /**
     * @param array<mixed> $messages The conversation history: [['role' => 'user'|'assistant', 'content' => '...'], ...]
     * @param array<mixed> $tools Tool definitions (optional)
     * @param array<mixed> $config Model configuration (optional)
     * @param string|null $systemInstruction Optional system instruction
     * @return array<mixed>
     */
    public function generateContent(array $messages, array $tools = [], array $config = [], ?string $systemInstruction = null): array;

    /**
     * Parses the raw provider response to extract either text or tool calls.
     *
     * @param array<mixed> $response
     * @return array{text:string, toolCalls:array<int, array{name:string, args:array<mixed>, id:?string}>}
     */
    public function parseResponse(array $response): array;
}

