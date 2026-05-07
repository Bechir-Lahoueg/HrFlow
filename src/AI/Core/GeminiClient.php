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

    public function chatStructured(string $prompt, string $systemPrompt): array
    {
        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]],
            ],
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 1024,
                'responseMimeType' => 'application/json',
            ],
        ];

        try {
            $response = $this->httpClient->request('POST', self::BASE_URL, [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Goog-Api-Key' => $this->apiKey,
                ],
                'timeout' => 30,
            ]);

            $content = $response->getContent();
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Gemini JSON decode error', ['error' => json_last_error_msg()]);
                return [];
            }

            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $result = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Gemini result JSON decode error', ['text' => $text]);
                return [];
            }

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Gemini chatStructured error', ['message' => $e->getMessage()]);
            return [];
        }
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        $contents = $this->buildContents($request->messages);
        $generationConfig = [
            'temperature' => 0.9,
            'topP' => 0.95,
            'topK' => 40,
            'maxOutputTokens' => 2048,
        ];

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
            $payload['tools'] = $this->buildTools($request->tools);
        }

        $maxRetries = 2;
        $retryCount = 0;
        $data = null;

        while ($retryCount <= $maxRetries) {
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

                if (in_array($statusCode, [429, 500, 503], true) && $retryCount < $maxRetries) {
                    ++$retryCount;
                    $this->logger->warning('Gemini API ' . $statusCode . ', retrying', ['attempt' => $retryCount]);
                    sleep(2);
                    continue;
                }
                
                if ($statusCode >= 400) {
                    $this->logger->error('Gemini API error', [
                        'status' => $statusCode,
                        'response' => $content,
                    ]);
                    return new ChatResponse(
                        content: 'Erreur de connexion au service IA (code ' . $statusCode . '). Veuillez réessayer.',
                        toolCalls: [],
                    );
                }
                
                $data = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->error('Gemini JSON decode error', [
                        'error' => json_last_error_msg(),
                    ]);
                    return new ChatResponse(
                        content: 'Erreur de traitement de la réponse IA.',
                        toolCalls: [],
                    );
                }
                
                break;
            } catch (ClientExceptionInterface|ServerExceptionInterface $e) {
                if ($retryCount < $maxRetries) {
                    ++$retryCount;
                    $this->logger->warning('Gemini HTTP error, retrying', ['attempt' => $retryCount, 'message' => $e->getMessage()]);
                    sleep(2);
                    continue;
                }
                $this->logger->error('Gemini HTTP error', ['message' => $e->getMessage()]);
                return new ChatResponse(
                    content: 'Erreur de communication avec le service IA. Veuillez réessayer.',
                    toolCalls: [],
                );
            } catch (\Exception $e) {
                $this->logger->error('Gemini unexpected error', ['message' => $e->getMessage()]);
                return new ChatResponse(
                    content: 'Une erreur inattendue est survenue. Veuillez réessayer.',
                    toolCalls: [],
                );
            }
        }

        return $this->parseResponse($data ?? []);
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
                $contents[] = [
                    'role' => $message->role,
                    'parts' => [
                        ['text' => $message->content],
                    ],
                ];
            }
        }

        return $contents;
    }

    /**
     * @param array<int, \App\AI\Contract\ToolInterface|array<string, mixed>> $tools
     * @return array<int, mixed>
     */
    private function buildTools(array $tools): array
    {
        $functionDeclarations = [];
        foreach ($tools as $tool) {
            if (!($tool instanceof \App\AI\Contract\ToolInterface)) {
                continue;
            }
            $functionDeclarations[] = $tool->getDefinition();
        }

        return [
            [
                'functionDeclarations' => $functionDeclarations,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseResponse(array $data): ChatResponse
    {
        $content = '';
        $toolCalls = [];

        if (isset($data['candidates'])) {
            $candidate = $data['candidates'][0] ?? null;
            if ($candidate && isset($candidate['content']['parts'])) {
                foreach ($candidate['content']['parts'] as $part) {
                    if (isset($part['text'])) {
                        $content .= $part['text'];
                    }
                    if (isset($part['functionCall'])) {
                        $toolCalls[] = new ToolCall(
                            id: $part['functionCall']['id'] ?? bin2hex(random_bytes(8)),
                            name: $part['functionCall']['name'],
                            arguments: $part['functionCall']['args'] ?? [],
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