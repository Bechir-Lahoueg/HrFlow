<?php

namespace App\Service\AI;

interface LlmClientInterface
{
    /**
     * @param array $messages The conversation history: [['role' => 'user'|'assistant', 'content' => '...'], ...]
     * @param array $tools Tool definitions (optional)
     * @param array $config Model configuration (optional)
     * @param string|null $systemInstruction Optional system instruction
     */
    public function generateContent(array $messages, array $tools = [], array $config = [], ?string $systemInstruction = null): array;

    /**
     * Parses the raw provider response to extract either text or tool calls.
     *
     * @return array{text:string, toolCalls:array<int, array{name:string, args:array, id:?string}>}
     */
    public function parseResponse(array $response): array;
}

