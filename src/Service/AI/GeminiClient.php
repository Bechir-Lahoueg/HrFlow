<?php

namespace App\Service\AI;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiClient implements LlmClientInterface
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    private string $apiKey;
    private string $model;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        ParameterBagInterface $params,
        string $model = 'gemini-2.5-flash-lite'
    ) {
        $this->apiKey = (string) $params->get('gemini_api_key');
        $this->model = $model;
    }

    public function generateContent(array $messages, array $tools = [], array $config = [], ?string $systemInstruction = null): array
    {
        $modelName = $config['model'] ?? $this->model;
        $url = sprintf('%s/models/%s:generateContent?key=%s', self::BASE_URL, $modelName, $this->apiKey);

        $body = [
            'contents' => $this->normalizeMessages($messages),
        ];

        if (!empty($tools)) {
            $cleanedTools = array_map(function($tool) {
                // Convert to Gemini's expected format (camelCase)
                $geminiTool = [
                    'name' => $tool['name'] ?? '',
                    'description' => $tool['description'] ?? '',
                ];
                if (isset($tool['parameters'])) {
                    $geminiTool['parameters'] = $this->cleanSchema($tool['parameters']);
                }
                return $geminiTool;
            }, array_values($tools));

            // Gemini API uses camelCase for functionDeclarations
            $body['tools'] = [
                ['functionDeclarations' => $cleanedTools]
            ];

            // Enable function calling mode (AUTO lets model decide when to call)
            $body['toolConfig'] = [
                'functionCallingConfig' => [
                    'mode' => 'AUTO'
                ]
            ];
        }

        if ($systemInstruction) {
            $body['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]]
            ];
        }

        $body['generationConfig'] = [
            'temperature' => (float) ($config['temperature'] ?? 0.1),
        ];

        $this->logger->info('Gemini request', [
            'model' => $modelName,
            'has_tools' => !empty($tools),
        ]);

        $response = $this->httpClient->request('POST', $url, [
            'json' => $body,
        ]);

        $data = $response->toArray(false);
        if (isset($data['error'])) {
            $this->logger->error('Gemini error', ['error' => $data['error']]);
        }

        return $data;
    }

    private function normalizeMessages(array $messages): array
    {
        $out = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            $geminiRole = ($role === 'assistant') ? 'model' : 'user';
            $parts = [];
            
            if (!empty($msg['content'])) {
                $parts[] = ['text' => $msg['content']];
            }

            // Clean history from previous assistant tool_calls
            if (isset($msg['tool_calls']) && is_array($msg['tool_calls'])) {
                foreach ($msg['tool_calls'] as $tc) {
                    $parts[] = [
                        'functionCall' => [
                            'name' => $tc['function']['name'] ?? $tc['name'] ?? '',
                            'args' => is_string($tc['function']['arguments'] ?? null) 
                                ? json_decode($tc['function']['arguments'], true) 
                                : ($tc['function']['arguments'] ?? $tc['args'] ?? [])
                        ]
                    ];
                }
            }

            if ($role === 'tool') {
                $geminiRole = 'user';
                // Gemini 3 requires the function response ID to match the call ID
                $parts = [[
                    'functionResponse' => [
                        'name' => $msg['name'] ?? 'unknown',
                        'id' => $msg['tool_call_id'] ?? 'call_' . uniqid(),
                        'response' => is_string($msg['content'] ?? null) 
                            ? ['result' => $msg['content']] 
                            : ($msg['content'] ?? ['result' => ''])
                    ]
                ]];
            }

            if (!empty($parts)) {
                $out[] = [
                    'role' => $geminiRole,
                    'parts' => $parts
                ];
            }
        }
        return $out;
    }

    private function cleanSchema(array $schema): array
    {
        unset($schema['additionalProperties']);
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $key => $prop) {
                if (is_array($prop)) {
                    $schema['properties'][$key] = $this->cleanSchema($prop);
                }
            }
        }
        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->cleanSchema($schema['items']);
        }
        return $schema;
    }

    public function parseResponse(array $response): array
    {
        if (isset($response['error'])) {
            $message = $response['error']['message'] ?? 'Gemini API error';
            throw new \RuntimeException($message);
        }

        $candidate = $response['candidates'][0] ?? null;
        if (!$candidate) {
            throw new \RuntimeException('No candidates in Gemini response.');
        }

        $result = [
            'text' => '',
            'toolCalls' => [],
        ];

        foreach ($candidate['content']['parts'] ?? [] as $part) {
            if (isset($part['text'])) {
                $result['text'] .= $part['text'];
            }
            if (isset($part['functionCall'])) {
                $result['toolCalls'][] = [
                    'name' => $part['functionCall']['name'] ?? '',
                    'args' => $part['functionCall']['args'] ?? [],
                    'id' => $part['functionCall']['id'] ?? null,
                ];
            }
        }

        return $result;
    }
}
