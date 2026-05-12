<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Contract\LlmClientInterface;
use App\AI\Contract\ToolInterface;
use App\AI\Infrastructure\ChatMessage;
use App\AI\Infrastructure\ChatRequest;
use App\AI\Infrastructure\ChatResponse;
use App\AI\Infrastructure\ToolCall;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

final class GeminiClient implements LlmClientInterface
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly LoggerInterface $logger,
    ) {}

    public function chat(ChatRequest $request): ChatResponse
    {
        $contents = $this->buildContents($request->messages);
        $generationConfig = [
            'temperature' => 0.1,
            'topP' => 0.95,
            'topK' => 40,
            'maxOutputTokens' => 2048,
        ];

        if ($request->responseMimeType !== null) {
            $generationConfig['responseMimeType'] = $request->responseMimeType;
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => $generationConfig,
        ];

        if ($request->systemPrompt !== '') {
            $payload['system_instruction'] = [
                'parts' => [
                    ['text' => $request->systemPrompt],
                ],
            ];
        }

        if (\count($request->tools) > 0) {
            $tools = $this->buildTools($request->tools);
            if (!empty($tools)) {
                $payload['tools'] = $tools;
            }
        }

        $statusCode = null;
        $content = null;

        try {
            $response = $this->httpClient->request('POST', self::BASE_URL, [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Goog-Api-Key' => $this->apiKey,
                ],
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);

            if ($statusCode >= 400) {
                $this->logger->error('Gemini API error', [
                    'status' => $statusCode,
                    'response' => \substr($content, 0, 2000),
                    'intent_parse' => $request->responseMimeType !== null,
                    'has_tools' => \count($request->tools) > 0,
                ]);
                return new ChatResponse(
                    content: 'Erreur de connexion au service IA (code ' . $statusCode . '). Veuillez réessayer.',
                    toolCalls: [],
                );
            }

            $data = \json_decode($content, true);
            if (\json_last_error() !== \JSON_ERROR_NONE) {
                $this->logger->error('Gemini JSON decode error', [
                    'error' => \json_last_error_msg(),
                ]);
                return new ChatResponse(
                    content: 'Erreur de traitement de la réponse IA.',
                    toolCalls: [],
                );
            }

            return $this->parseResponse($data);
        } catch (\Throwable $e) {
            $this->logger->error('Gemini request failed', [
                'message' => $e->getMessage(),
                'status' => $statusCode,
            ]);
            return new ChatResponse(
                content: 'Erreur de communication avec le service IA. Veuillez réessayer.',
                toolCalls: [],
            );
        }
    }

    private function parseResponse(array $data): ChatResponse
    {
        $content = '';
        $toolCalls = [];

        if (!isset($data['candidates'][0]['content']['parts'])) {
            return new ChatResponse(content: '', toolCalls: []);
        }

        foreach ($data['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['text'])) {
                $content .= $part['text'];
            }
            if (isset($part['functionCall'])) {
                $toolCalls[] = new ToolCall(
                    id: $part['functionCall']['id'] ?? \bin2hex(\random_bytes(8)),
                    name: $part['functionCall']['name'],
                    arguments: $part['functionCall']['args'] ?? [],
                );
            }
        }

        return new ChatResponse(
            content: $content,
            toolCalls: $toolCalls,
        );
    }

    /**
     * @param ChatMessage[] $messages
     * @return array<int, mixed>
     */
    private function buildContents(array $messages): array
    {
        $contents = [];

        foreach ($messages as $message) {
            if ($message->isToolCall()) {
                $args = $message->toolCallArgs ?? [];
                if (empty($args)) {
                    $args = new \stdClass();
                }
                $contents[] = [
                    'role' => 'model',
                    'parts' => [
                        [
                            'functionCall' => [
                                'name' => $message->toolCallName,
                                'args' => $args,
                            ],
                        ],
                    ],
                ];
            } elseif ($message->isToolResponse()) {
                $contents[] = [
                    'role' => 'user',
                    'parts' => [
                        [
                            'functionResponse' => [
                                'name' => $message->toolCallName,
                                'response' => [
                                    'name' => $message->toolCallName,
                                    'content' => $message->content,
                                ],
                            ],
                        ],
                    ],
                ];
            } else {
                $role = $message->role === 'model' ? 'model' : 'user';
                $contents[] = [
                    'role' => $role,
                    'parts' => [
                        ['text' => $message->content],
                    ],
                ];
            }
        }

        return $contents;
    }

    /**
     * @param array<int, ToolInterface|array<string, mixed>> $tools
     * @return array<int, mixed>
     */
    private function buildTools(array $tools): array
    {
        $functionDeclarations = [];
        foreach ($tools as $tool) {
            if (!$tool instanceof ToolInterface) {
                continue;
            }
            $definition = $tool->getDefinition();
            if (!isset($definition['name'])) {
                continue;
            }
            $functionDeclarations[] = $this->normalizeToolDefinition($definition);
        }

        if (empty($functionDeclarations)) {
            return [];
        }

        return [
            ['functionDeclarations' => $functionDeclarations],
        ];
    }

    private function normalizeToolDefinition(array $definition): array
    {
        $normalized = [
            'name' => $definition['name'],
            'description' => $definition['description'] ?? '',
        ];

        if (isset($definition['parameters'])) {
            $normalized['parameters'] = $this->normalizeSchema($definition['parameters']);
        }

        return $normalized;
    }

    private function normalizeSchema(array $schema): array
    {
        $normalized = [
            'type' => 'OBJECT',
            'properties' => [],
        ];

        if (!isset($schema['properties']) && !isset($schema['type'])) {
            return $normalized;
        }

        if (isset($schema['type'])) {
            $normalized['type'] = \strtoupper($schema['type']);
        }

        if (!isset($schema['properties'])) {
            return $normalized;
        }

        foreach ($schema['properties'] as $key => $prop) {
            if (\is_array($prop)) {
                $normalized['properties'][$key] = [
                    'type' => \strtoupper($prop['type'] ?? 'STRING'),
                    'description' => $prop['description'] ?? '',
                ];
                if (isset($prop['enum'])) {
                    $normalized['properties'][$key]['enum'] = $prop['enum'];
                }
                if (isset($prop['required'])) {
                    $normalized['required'] = $prop['required'];
                }
            } else {
                $normalized['properties'][$key] = [
                    'type' => 'STRING',
                    'description' => \is_string($prop) ? $prop : '',
                ];
            }
        }

        return $normalized;
    }
}
