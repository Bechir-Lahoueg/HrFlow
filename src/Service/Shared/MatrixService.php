<?php

namespace App\Service\Shared;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MatrixService
{
    private ?string $lastError = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $matrixClientUrl,
        private readonly string $matrixAccessToken,
        private readonly string $matrixBotUserId,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->matrixClientUrl !== ''
            && $this->matrixAccessToken !== ''
            && $this->matrixBotUserId !== '';
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function createProjectRoom(string $projectName): ?string
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->lastError = 'Configuration Matrix manquante.';
            return null;
        }

        try {
            $name = 'HRFlow - ' . trim($projectName);
            $aliasPart = $this->sanitizeAliasPart($projectName . '-' . substr(sha1($projectName . microtime(true)), 0, 8));

            $response = $this->request('POST', '/_matrix/client/v3/createRoom', [
                'json' => [
                    'name' => $name,
                    'topic' => 'Chat d\'equipe du projet ' . trim($projectName),
                    'preset' => 'private_chat',
                    'room_alias_name' => 'hrflow-' . $aliasPart,
                    'creation_content' => ['m.federate' => false],
                ],
            ]);

            if ($response['status'] < 200 || $response['status'] >= 300) {
                $this->lastError = $this->formatApiError($response['status'], $response['body']);
                return null;
            }

            $roomId = $response['body']['room_id'] ?? null;
            return is_string($roomId) && $roomId !== '' ? $roomId : null;
        } catch (\Throwable) {
            $this->lastError = 'Exception locale durant la creation de room.';
            return null;
        }
    }

    public function sendMessage(string $roomId, string $senderLabel, string $message): bool
    {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->lastError = 'Configuration Matrix manquante.';
            return false;
        }

        $sender = trim($senderLabel);
        $body = trim($message);

        if ($roomId === '' || $body === '') {
            $this->lastError = 'Room ou message vide.';
            return false;
        }

        try {
            $txnId = rawurlencode((string) round(microtime(true) * 1000) . '-' . bin2hex(random_bytes(4)));

            $response = $this->request(
                'PUT',
                '/_matrix/client/v3/rooms/' . rawurlencode($roomId) . '/send/m.room.message/' . $txnId,
                [
                    'json' => [
                        'msgtype' => 'm.text',
                        'body' => '[' . ($sender !== '' ? $sender : 'Utilisateur') . '] ' . $body,
                    ],
                ]
            );

            if ($response['status'] < 200 || $response['status'] >= 300) {
                $this->lastError = $this->formatApiError($response['status'], $response['body']);
                return false;
            }

            return true;
        } catch (\Throwable) {
            $this->lastError = 'Exception locale durant l\'envoi du message.';
            return false;
        }
    }

    /**
     * @return array{messages:array<int,array<string,mixed>>,next:string|null}
     */
    public function getMessages(string $roomId, ?string $from = null, int $limit = 40): array
    {
        $this->lastError = null;

        if (!$this->isConfigured() || $roomId === '') {
            return ['messages' => [], 'next' => null];
        }

        try {
            $limit = max(1, min(80, $limit));
            $query = '/_matrix/client/v3/rooms/' . rawurlencode($roomId) . '/messages?dir=b&limit=' . $limit;
            if (is_string($from) && $from !== '') {
                $query .= '&from=' . rawurlencode($from);
            }

            $response = $this->request('GET', $query);
            if ($response['status'] < 200 || $response['status'] >= 300) {
                $this->lastError = $this->formatApiError($response['status'], $response['body']);
                return ['messages' => [], 'next' => null];
            }

            $chunk = $response['body']['chunk'] ?? [];
            if (!is_array($chunk)) {
                return ['messages' => [], 'next' => null];
            }

            $messages = [];
            foreach ($chunk as $event) {
                if (!is_array($event)) {
                    continue;
                }

                if (($event['type'] ?? '') !== 'm.room.message') {
                    continue;
                }

                $content = $event['content'] ?? [];
                if (!is_array($content)) {
                    continue;
                }

                if (($content['msgtype'] ?? '') !== 'm.text') {
                    continue;
                }

                $body = (string) ($content['body'] ?? '');
                if ($body === '') {
                    continue;
                }

                $messages[] = [
                    'event_id' => (string) ($event['event_id'] ?? ''),
                    'sender' => (string) ($event['sender'] ?? ''),
                    'origin_server_ts' => (int) ($event['origin_server_ts'] ?? 0),
                    'body' => $body,
                ];
            }

            $messages = array_reverse($messages);

            $next = $response['body']['end'] ?? null;
            if (!is_string($next) || $next === '') {
                $next = null;
            }

            return [
                'messages' => $messages,
                'next' => $next,
            ];
        } catch (\Throwable) {
            $this->lastError = 'Exception locale durant la lecture des messages.';
            return ['messages' => [], 'next' => null];
        }
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    private function request(string $method, string $path, array $options = []): array
    {
        $requestOptions = array_merge($options, [
            'headers' => array_merge($options['headers'] ?? [], [
                'Authorization' => 'Bearer ' . $this->matrixAccessToken,
                'Content-Type' => 'application/json',
            ]),
            'timeout' => 15,
        ]);

        $response = $this->httpClient->request($method, rtrim($this->matrixClientUrl, '/') . $path, $requestOptions);

        $rawBody = $response->getContent(false);
        $decodedBody = json_decode($rawBody, true);
        $body = is_array($decodedBody)
            ? $decodedBody
            : ['_raw' => $rawBody];

        return [
            'status' => $response->getStatusCode(),
            'body' => $body,
        ];
    }

    /**
     * @param array<string,mixed> $body
     */
    private function formatApiError(int $status, array $body): string
    {
        $msg = $body['error'] ?? ($body['errcode'] ?? ($body['_raw'] ?? 'Erreur Matrix inconnue'));
        return 'HTTP ' . $status . ' - ' . (string) $msg;
    }

    private function sanitizeAliasPart(string $input): string
    {
        $value = strtolower(trim($input));
        $value = preg_replace('/[^a-z0-9._=-]/', '-', $value) ?? '';
        $value = trim($value, '-');

        if ($value === '') {
            return 'project-' . substr(sha1((string) microtime(true)), 0, 8);
        }

        return substr($value, 0, 48);
    }
}


