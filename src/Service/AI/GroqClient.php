<?php

namespace App\Service\AI;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GroqClient implements LlmClientInterface
{
    private const BASE_URL = 'https://api.groq.com/openai/v1';

    private string $apiKey;
    private string $model;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        ParameterBagInterface $params,
        string $model = 'llama-3.3-70b-versatile'
    ) {
        $this->apiKey = (string) $params->get('groq_api_key');
        $this->model = $model;
    }

    public function generateContent(array $messages, array $tools = [], array $config = [], ?string $systemInstruction = null): array
    {
        $url = self::BASE_URL . '/chat/completions';

        $openAiMessages = $this->normalizeMessages($messages, $systemInstruction);

        $body = [
            'model' => $config['model'] ?? $this->model,
            'messages' => $openAiMessages,
        ];

        // Tools (local tool calling / function calling)
        if (!empty($tools)) {
            $body['tools'] = array_map(static function (array $t) {
                return [
                    'type' => 'function',
                    'function' => [
                        'name' => $t['name'] ?? '',
                        'description' => $t['description'] ?? '',
                        'parameters' => $t['parameters'] ?? ['type' => 'object', 'properties' => []],
                    ],
                ];
            }, $tools);

            // Let the model decide. Caller can override by setting tool_choice in $config.
            $body['tool_choice'] = $config['tool_choice'] ?? 'auto';
        } elseif (isset($config['tool_choice']) && $config['tool_choice'] === 'none') {
            // Explicitly disable tool calling - don't pass tools array
        }

        // Structured outputs (JSON schema). Note: Groq docs say tool-use + structured outputs not supported together.
        // Also skip JSON mode when tool_choice is 'none' to avoid model trying to call tools
        if (!isset($config['tool_choice']) || $config['tool_choice'] !== 'none') {
            if (!empty($config['responseJsonSchema']) || !empty($config['response_schema'])) {
                $schema = $config['responseJsonSchema'] ?? $config['response_schema'];
                $body['response_format'] = [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => $config['schema_name'] ?? 'hrflow_plan',
                        'strict' => (bool) ($config['strict'] ?? false),
                        'schema' => $schema,
                    ],
                ];
            } elseif (($config['responseMimeType'] ?? $config['response_mime_type'] ?? null) === 'application/json') {
                // JSON object mode (best-effort valid JSON).
                $body['response_format'] = ['type' => 'json_object'];
            }
        }

        if (isset($config['max_output_tokens'])) {
            $body['max_completion_tokens'] = (int) $config['max_output_tokens'];
        } elseif (isset($config['max_tokens'])) {
            $body['max_tokens'] = (int) $config['max_tokens'];
        }

        if (isset($config['temperature'])) {
            $body['temperature'] = (float) $config['temperature'];
        }

        if (isset($config['parallel_tool_calls'])) {
            $body['parallel_tool_calls'] = (bool) $config['parallel_tool_calls'];
        }

        $this->logger->info('Groq request', [
            'model' => $body['model'],
            'has_tools' => !empty($tools),
            'has_response_format' => isset($body['response_format']),
        ]);

        $response = $this->httpClient->request('POST', $url, [
            'json' => $body,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);

        $data = $response->toArray(false);
        if (isset($data['error'])) {
            $this->logger->error('Groq error', ['error' => $data['error']]);
        } else {
            $this->logger->info('Groq response', [
                'model' => $body['model'],
                'has_choices' => isset($data['choices']),
            ]);
        }

        return $data;
    }

    /**
     * Normalizes application messages to OpenAI/Groq Chat Completions format.
     *
     * Supports roles: system, user, assistant, tool.
     * Preserves tool-call metadata (tool_calls, tool_call_id, name) when present.
     */
    private function normalizeMessages(array $messages, ?string $systemInstruction = null): array
    {
        $out = [];

        if ($systemInstruction) {
            $out[] = [
                'role' => 'system',
                'content' => $systemInstruction,
            ];
        }

        foreach ($messages as $msg) {
            $role = (string) ($msg['role'] ?? 'user');

            if ($role === 'system') {
                $out[] = [
                    'role' => 'system',
                    'content' => (string) ($msg['content'] ?? ''),
                ];
                continue;
            }

            if ($role === 'assistant') {
                $assistant = [
                    'role' => 'assistant',
                    'content' => $msg['content'] ?? null,
                ];

                if (isset($msg['tool_calls']) && is_array($msg['tool_calls'])) {
                    $assistant['tool_calls'] = $msg['tool_calls'];
                }

                $out[] = $assistant;
                continue;
            }

            if ($role === 'tool') {
                $toolMsg = [
                    'role' => 'tool',
                    'content' => (string) ($msg['content'] ?? ''),
                ];

                if (isset($msg['tool_call_id'])) {
                    $toolMsg['tool_call_id'] = (string) $msg['tool_call_id'];
                }
                if (isset($msg['name'])) {
                    $toolMsg['name'] = (string) $msg['name'];
                }

                $out[] = $toolMsg;
                continue;
            }

            // default: user
            $out[] = [
                'role' => 'user',
                'content' => (string) ($msg['content'] ?? ''),
            ];
        }

        return $out;
    }

    public function parseResponse(array $response): array
    {
        if (isset($response['error'])) {
            $message = is_array($response['error']) ? ($response['error']['message'] ?? 'Groq API error') : 'Groq API error';
            throw new \RuntimeException($message);
        }

        $choice = $response['choices'][0] ?? null;
        $message = $choice['message'] ?? null;
        if (!$message) {
            throw new \RuntimeException('No choices in Groq response.');
        }

        $result = [
            'text' => (string) ($message['content'] ?? ''),
            'toolCalls' => [],
        ];

        $toolCalls = $message['tool_calls'] ?? [];
        foreach ($toolCalls as $tc) {
            $name = $tc['function']['name'] ?? null;
            $argsJson = $tc['function']['arguments'] ?? '{}';
            $args = json_decode($argsJson, true);
            if (!is_array($args)) {
                $args = [];
            }

            if ($name) {
                $result['toolCalls'][] = [
                    'name' => $name,
                    'args' => $args,
                    'id' => $tc['id'] ?? null,
                ];
            }
        }

        return $result;
    }
}

