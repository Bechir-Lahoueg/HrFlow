<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Contract\LlmClientInterface;
use App\AI\Contract\ToolInterface;
use App\AI\Infrastructure\ChatMessage;
use App\AI\Infrastructure\ChatRequest;
use App\AI\Infrastructure\ChatResponse;
use App\AI\Infrastructure\ToolCall;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GroqClient implements LlmClientInterface
{
    private const BASE_URL = 'https://api.groq.com/openai/v1';
    private const MODEL = 'openai/gpt-oss-120b';
    private const MAX_CONTENT_LENGTH = 3000;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $groqApi,
        private readonly LoggerInterface $logger,
    ) {}

    public function chat(ChatRequest $request): ChatResponse
    {
        $messages = $this->buildMessages($request->messages, $request->systemPrompt);

        $payload = [
            'model' => self::MODEL,
            'messages' => $messages,
            'max_completion_tokens' => 8192,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'stream' => false,
            'reasoning_effort' => 'medium',
        ];

        if (\count($request->tools) > 0) {
            $payload['tools'] = $this->buildTools($request->tools);
            $payload['tool_choice'] = 'auto';
        }

        $url = self::BASE_URL . '/chat/completions';

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => $payload,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApi,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 60,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);

            if ($statusCode >= 400) {
                $this->logger->error('Groq API error', [
                    'status' => $statusCode,
                    'response' => $content,
                ]);
                return new ChatResponse(
                    content: 'Erreur de connexion au service IA (code ' . $statusCode . '). Veuillez r\xe9essayer.',
                    toolCalls: [],
                );
            }

            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Groq JSON decode error', [
                    'error' => json_last_error_msg(),
                ]);
                return new ChatResponse(
                    content: 'Erreur de traitement de la r\xe9ponse IA.',
                    toolCalls: [],
                );
            }
        } catch (ClientExceptionInterface|ServerExceptionInterface $e) {
            $this->logger->error('Groq HTTP error', ['message' => $e->getMessage()]);
            return new ChatResponse(
                content: 'Erreur de communication avec le service IA. Veuillez r\xe9essayer.',
                toolCalls: [],
            );
        } catch (\Exception $e) {
            $this->logger->error('Groq unexpected error', ['message' => $e->getMessage()]);
            return new ChatResponse(
                content: 'Une erreur inattendue est survenue. Veuillez r\xe9essayer.',
                toolCalls: [],
            );
        }

        return $this->parseResponse($data);
    }

    /**
     * @param ChatMessage[] $messages
     * @return array<array{role: string, content: string}>
     */
    private function buildMessages(array $messages, string $systemPrompt): array
    {
        $out = [];

        if ($systemPrompt !== '') {
            $out[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }

        foreach ($messages as $message) {
            $content = $message->content;
            if (\strlen($content) > self::MAX_CONTENT_LENGTH) {
                $content = \substr($content, 0, self::MAX_CONTENT_LENGTH) . "\n... [truncated]";
            }
            $out[] = [
                'role' => $message->role,
                'content' => $content,
            ];
        }

        return $out;
    }

    /**
     * @param ToolInterface[]|array<int, array<string, mixed>> $tools
     * @return array<int, array{type: string, function: array<string, mixed>}>
     */
    private function buildTools(array $tools): array
    {
        $functionDeclarations = [];
        foreach ($tools as $tool) {
            if (!($tool instanceof ToolInterface)) {
                continue;
            }
            $definition = $tool->getDefinition();
            $functionDeclarations[] = [
                'type' => 'function',
                'function' => [
                    'name' => $definition['name'] ?? $tool->getName(),
                    'description' => $definition['description'] ?? '',
                    'parameters' => $definition['parameters'] ?? ['type' => 'object', 'properties' => []],
                ],
            ];
        }

        return $functionDeclarations;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseResponse(array $data): ChatResponse
    {
        $content = '';
        $toolCalls = [];

        if (isset($data['error'])) {
            $message = $data['error']['message'] ?? 'Groq API error';
            $this->logger->error('Groq API returned error', ['error' => $data['error']]);
            return new ChatResponse(
                content: 'Erreur du service IA: ' . $message,
                toolCalls: [],
            );
        }

        if (isset($data['choices'])) {
            $candidate = $data['choices'][0] ?? null;
            if ($candidate && isset($candidate['message'])) {
                $message = $candidate['message'];
                $content = $message['content'] ?? '';

                if (isset($message['tool_calls']) && is_array($message['tool_calls'])) {
                    foreach ($message['tool_calls'] as $tc) {
                        $args = [];
                        if (isset($tc['function']['arguments'])) {
                            $decoded = json_decode($tc['function']['arguments'], true);
                            if (is_array($decoded)) {
                                $args = $decoded;
                            }
                        }

                        $toolCalls[] = new ToolCall(
                            id: $tc['id'] ?? 'call_' . bin2hex(random_bytes(8)),
                            name: $tc['function']['name'] ?? '',
                            arguments: $args,
                        );
                    }
                }
            }
        }

        return new ChatResponse(
            content: $content,
            toolCalls: $toolCalls,
        );
    }
}
