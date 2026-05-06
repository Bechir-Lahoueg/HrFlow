<?php

namespace App\Service\Shared;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MatrixService
{
    private ?string $lastError = null;
    private ?int $lastErrorStatus = null;
    private ?string $lastErrorCode = null;

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

    public function getLastErrorStatus(): ?int
    {
        return $this->lastErrorStatus;
    }

    public function getLastErrorCode(): ?string
    {
        return $this->lastErrorCode;
    }

    public function createProjectRoom(string $projectName): ?string
    {
        $this->resetErrorContext();

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
                $this->captureApiErrorContext($response['status'], $response['body']);
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
        $this->resetErrorContext();

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
                $this->captureApiErrorContext($response['status'], $response['body']);
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
     * @return array{mxc:string,name:string,mime:string,size:int,download_url:string}|null
     */
    public function uploadMedia(UploadedFile $file): ?array
    {
        $this->resetErrorContext();

        if (!$this->isConfigured()) {
            $this->lastError = 'Configuration Matrix manquante.';
            return null;
        }

        $path = $file->getPathname();
        $binary = @file_get_contents($path);
        if (!is_string($binary) || $binary === '') {
            $this->lastError = 'Impossible de lire le fichier a envoyer.';
            return null;
        }

        $filename = $file->getClientOriginalName() ?: ('upload-' . time());
        $mime = $this->resolveUploadedFileMimeType($file);
        $size = (int) $file->getSize();

        try {
            $response = $this->request(
                'POST',
                '/_matrix/media/v3/upload?filename=' . rawurlencode($filename),
                [
                    'headers' => ['Content-Type' => $mime],
                    'body' => $binary,
                ]
            );

            if ($response['status'] < 200 || $response['status'] >= 300) {
                $this->captureApiErrorContext($response['status'], $response['body']);
                $this->lastError = $this->formatApiError($response['status'], $response['body']);
                return null;
            }

            $mxc = $response['body']['content_uri'] ?? null;
            if (!is_string($mxc) || $mxc === '') {
                $this->lastError = 'Reponse Matrix invalide (content_uri manquant).';
                return null;
            }

            return [
                'mxc' => $mxc,
                'name' => $filename,
                'mime' => $mime,
                'size' => $size,
                'download_url' => $this->mxcToDownloadUrl($mxc) ?? '',
            ];
        } catch (\Throwable) {
            $this->lastError = 'Exception locale durant l\'upload du media.';
            return null;
        }
    }

    /**
     * @param array{mxc?:string,url?:string,name:string,mime:string,size:int} $media
     */
    public function sendMediaMessage(string $roomId, string $senderLabel, array $media, string $msgType): bool
    {
        $this->resetErrorContext();

        if (!$this->isConfigured()) {
            $this->lastError = 'Configuration Matrix manquante.';
            return false;
        }

        $uri = (string) ($media['mxc'] ?? ($media['url'] ?? ''));
        $name = trim((string) $media['name']);
        $mime = trim((string) $media['mime']);
        $size = (int) $media['size'];
        $sender = trim($senderLabel);

        if ($roomId === '' || $uri === '') {
            $this->lastError = 'Room ou media invalide.';
            return false;
        }

        $allowed = ['m.file', 'm.image', 'm.video', 'm.audio'];
        if (!in_array($msgType, $allowed, true)) {
            $msgType = 'm.file';
        }

        $isMxc = str_starts_with($uri, 'mxc://');

        $txnId = rawurlencode((string) round(microtime(true) * 1000) . '-' . bin2hex(random_bytes(4)));
        $content = [
            'msgtype' => $msgType,
            'body' => '[' . ($sender !== '' ? $sender : 'Utilisateur') . '] ' . $name,
            'filename' => $name,
            'info' => [
                'mimetype' => $mime,
                'size' => max(0, $size),
            ],
        ];

        if ($isMxc) {
            $content['url'] = $uri;
        } else {
            // Fallback local: message Matrix 100% valide + metadonnees pour le rendu cote app.
            $content = [
                'msgtype' => 'm.text',
                'body' => '[' . ($sender !== '' ? $sender : 'Utilisateur') . '] Fichier: ' . $name,
                'hrflow_attachment' => [
                    'url' => $uri,
                    'name' => $name,
                    'mimetype' => $mime,
                    'size' => max(0, $size),
                    'original_msgtype' => $msgType,
                ],
            ];
        }

        try {
            $response = $this->request(
                'PUT',
                '/_matrix/client/v3/rooms/' . rawurlencode($roomId) . '/send/m.room.message/' . $txnId,
                ['json' => $content]
            );

            if ($response['status'] < 200 || $response['status'] >= 300) {
                $this->captureApiErrorContext($response['status'], $response['body']);
                $this->lastError = $this->formatApiError($response['status'], $response['body']);
                return false;
            }

            return true;
        } catch (\Throwable) {
            $this->lastError = 'Exception locale durant l\'envoi du media.';
            return false;
        }
    }

    public function sendReaction(string $roomId, string $eventId, string $reaction): bool
    {
        $this->resetErrorContext();

        if (!$this->isConfigured()) {
            $this->lastError = 'Configuration Matrix manquante.';
            return false;
        }

        $eventId = trim($eventId);
        $reaction = trim($reaction);
        if ($roomId === '' || $eventId === '' || $reaction === '') {
            $this->lastError = 'Reaction invalide.';
            return false;
        }

        try {
            $txnId = rawurlencode((string) round(microtime(true) * 1000) . '-' . bin2hex(random_bytes(4)));
            $response = $this->request(
                'PUT',
                '/_matrix/client/v3/rooms/' . rawurlencode($roomId) . '/send/m.reaction/' . $txnId,
                [
                    'json' => [
                        'm.relates_to' => [
                            'rel_type' => 'm.annotation',
                            'event_id' => $eventId,
                            'key' => substr($reaction, 0, 16),
                        ],
                    ],
                ]
            );

            if ($response['status'] < 200 || $response['status'] >= 300) {
                $this->captureApiErrorContext($response['status'], $response['body']);
                $this->lastError = $this->formatApiError($response['status'], $response['body']);
                return false;
            }

            return true;
        } catch (\Throwable) {
            $this->lastError = 'Exception locale durant l\'envoi de la reaction.';
            return false;
        }
    }

    /**
     * @return array{content:string,mime:string,filename:string}|null
     */
    public function downloadMedia(string $mxc): ?array
    {
        $this->resetErrorContext();

        if (!$this->isConfigured()) {
            $this->lastError = 'Configuration Matrix manquante.';
            return null;
        }

        if (!str_starts_with($mxc, 'mxc://')) {
            $this->lastError = 'URI media Matrix invalide.';
            return null;
        }

        $mediaPath = substr($mxc, 6);
        if ($mediaPath === '' || !str_contains($mediaPath, '/')) {
            $this->lastError = 'URI media Matrix incomplet.';
            return null;
        }

        [$serverName, $mediaId] = explode('/', $mediaPath, 2);
        if ($serverName === '' || $mediaId === '') {
            $this->lastError = 'URI media Matrix incomplet.';
            return null;
        }

        $base = rtrim($this->matrixClientUrl, '/');
        $encodedServer = rawurlencode($serverName);
        $encodedMedia = rawurlencode($mediaId);

        $paths = [
            '/_matrix/media/v3/download/' . $encodedServer . '/' . $encodedMedia,
            '/_matrix/client/v1/media/download/' . $encodedServer . '/' . $encodedMedia,
            '/_matrix/media/r0/download/' . $encodedServer . '/' . $encodedMedia,
        ];

        $lastStatus = null;
        $lastBody = null;

        foreach ($paths as $path) {
            try {
                $response = $this->httpClient->request(
                    'GET',
                    $base . $path,
                    [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->matrixAccessToken,
                        ],
                        'timeout' => 30,
                    ]
                );

                $status = $response->getStatusCode();
                $content = $response->getContent(false);

                if ($status < 200 || $status >= 300) {
                    $decodedBody = json_decode($content, true);
                    $body = is_array($decodedBody) ? $decodedBody : ['_raw' => $content];
                    $lastStatus = $status;
                    $lastBody = $body;
                    continue;
                }

                $headers = $response->getHeaders(false);
                $mime = 'application/octet-stream';
                if (isset($headers['content-type'][0])) {
                    $mime = trim(explode(';', $headers['content-type'][0])[0]);
                }

                $filename = $mediaId;
                if (isset($headers['content-disposition'][0])) {
                    if (preg_match('/filename\*?="?([^";]+)"?/i', $headers['content-disposition'][0], $matches)) {
                        $filename = rawurldecode($matches[1]);
                    }
                }

                return [
                    'content' => $content,
                    'mime' => $mime !== '' ? $mime : 'application/octet-stream',
                    'filename' => $filename,
                ];
            } catch (\Throwable) {
                // On essaie le endpoint suivant pour compatibilite homeserver.
                continue;
            }
        }

        if ($lastStatus !== null && is_array($lastBody)) {
            $this->captureApiErrorContext($lastStatus, $lastBody);
            $this->lastError = $this->formatApiError($lastStatus, $lastBody);
        } else {
            $this->lastError = 'Exception locale durant le telechargement du media.';
        }

        return null;
    }

    /**
     * @return array{messages:array<int,array<string,mixed>>,next:string|null}
     */
    public function getMessages(string $roomId, ?string $from = null, int $limit = 40): array
    {
        $this->resetErrorContext();

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
                $this->captureApiErrorContext($response['status'], $response['body']);
                $this->lastError = $this->formatApiError($response['status'], $response['body']);
                return ['messages' => [], 'next' => null];
            }

            $chunk = $response['body']['chunk'] ?? [];
            if (!is_array($chunk)) {
                return ['messages' => [], 'next' => null];
            }

            $events = [];
            foreach ($chunk as $event) {
                if (!is_array($event)) {
                    continue;
                }

                $type = (string) ($event['type'] ?? '');
                $content = is_array($event['content'] ?? null) ? $event['content'] : [];
                if ($type === 'm.room.message') {
                    $msgType = (string) ($content['msgtype'] ?? '');
                    if (!in_array($msgType, ['m.text', 'm.file', 'm.image', 'm.video', 'm.audio'], true)) {
                        continue;
                    }

                    $body = (string) ($content['body'] ?? '');
                    $mxc = (string) ($content['url'] ?? '');
                    $externalUrl = (string) ($content['external_url'] ?? '');
                    $attachment = is_array($content['hrflow_attachment'] ?? null) ? $content['hrflow_attachment'] : [];
                    $attachmentUrl = (string) ($attachment['url'] ?? '');
                    $effectiveUrl = $mxc !== '' ? $mxc : $externalUrl;
                    if ($effectiveUrl === '' && $attachmentUrl !== '') {
                        $effectiveUrl = $attachmentUrl;
                    }

                    if ($msgType === 'm.text' && $attachmentUrl !== '') {
                        $originalMsgType = (string) ($attachment['original_msgtype'] ?? 'm.file');
                        if (in_array($originalMsgType, ['m.file', 'm.image', 'm.video', 'm.audio'], true)) {
                            $msgType = $originalMsgType;
                        } else {
                            $msgType = 'm.file';
                        }
                    }

                    $info = is_array($content['info'] ?? null) ? $content['info'] : [];
                    if (empty($info) && !empty($attachment)) {
                        $info = [
                            'mimetype' => (string) ($attachment['mimetype'] ?? ''),
                            'size' => (int) ($attachment['size'] ?? 0),
                        ];
                    }

                    if ($body === '' && $effectiveUrl === '') {
                        continue;
                    }

                    $events[] = [
                        'kind' => 'message',
                        'event_id' => (string) ($event['event_id'] ?? ''),
                        'sender' => (string) ($event['sender'] ?? ''),
                        'origin_server_ts' => (int) ($event['origin_server_ts'] ?? 0),
                        'msgtype' => $msgType,
                        'body' => $body,
                        'url' => $effectiveUrl,
                        'download_url' => $this->resolveDownloadUrl($effectiveUrl),
                        'info' => $info,
                    ];
                    continue;
                }

                if ($type !== 'm.reaction') {
                    continue;
                }

                $relates = is_array($content['m.relates_to'] ?? null) ? $content['m.relates_to'] : [];
                $targetEventId = (string) ($relates['event_id'] ?? '');
                $reactionKey = (string) ($relates['key'] ?? '');
                if ($targetEventId === '' || $reactionKey === '' || (string) ($relates['rel_type'] ?? '') !== 'm.annotation') {
                    continue;
                }

                $events[] = [
                    'kind' => 'reaction',
                    'event_id' => (string) ($event['event_id'] ?? ''),
                    'sender' => (string) ($event['sender'] ?? ''),
                    'origin_server_ts' => (int) ($event['origin_server_ts'] ?? 0),
                    'target_event_id' => $targetEventId,
                    'key' => $reactionKey,
                ];
            }

            $events = array_reverse($events);

            /**
             * @var array<int, array{kind:'message',event_id:string,sender:string,origin_server_ts:int,msgtype:string,body:string,url:string,download_url:string,info:array<string,mixed>}|array{kind:'reaction',event_id:string,sender:string,origin_server_ts:int,target_event_id:string,key:string}> $events
             */
            $messages = [];
            $messageIndexById = [];

            foreach ($events as $event) {
                if ($event['kind'] === 'message') {
                    $message = [
                        'event_id' => $event['event_id'],
                        'sender' => $event['sender'],
                        'origin_server_ts' => $event['origin_server_ts'],
                        'msgtype' => $event['msgtype'],
                        'body' => $event['body'],
                        'url' => $event['url'],
                        'download_url' => $event['download_url'],
                        'info' => $event['info'],
                        'reactions' => [],
                    ];

                    $messages[] = $message;
                    $messageIndexById[$message['event_id']] = count($messages) - 1;
                    continue;
                }

                $targetEventId = $event['target_event_id'];
                $key = $event['key'];
                if ($targetEventId === '' || $key === '') {
                    continue;
                }

                if (!isset($messageIndexById[$targetEventId])) {
                    continue;
                }

                $messageIndex = $messageIndexById[$targetEventId];
                if (!is_array($messages[$messageIndex] ?? null)) {
                    continue;
                }
                $reactions = $messages[$messageIndex]['reactions'];
                // @phpstan-ignore-next-line function.alreadyNarrowedType
                if (!is_array($reactions)) {
                    $reactions = [];
                }
                $reactions[$key] = ((int) ($reactions[$key] ?? 0)) + 1;
                $messages[$messageIndex]['reactions'] = $reactions;
            }

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
     * @param array<string,mixed> $options
     * @return array{status:int,body:array<string,mixed>}
     */
    private function request(string $method, string $path, array $options = []): array
    {
        $requestOptions = array_merge($options, [
            'headers' => array_merge([
                'Authorization' => 'Bearer ' . $this->matrixAccessToken,
            ], $options['headers'] ?? []),
            'timeout' => 15,
        ]);

        if (!isset($requestOptions['headers']['Content-Type']) && isset($requestOptions['json'])) {
            $requestOptions['headers']['Content-Type'] = 'application/json';
        }

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

    private function resetErrorContext(): void
    {
        $this->lastError = null;
        $this->lastErrorStatus = null;
        $this->lastErrorCode = null;
    }

    /**
     * @param array<string,mixed> $body
     */
    private function captureApiErrorContext(int $status, array $body): void
    {
        $this->lastErrorStatus = $status;

        $code = $body['errcode'] ?? null;
        $this->lastErrorCode = is_string($code) && $code !== '' ? $code : null;
    }

    private function mxcToDownloadUrl(string $mxc): ?string
    {
        if (!str_starts_with($mxc, 'mxc://')) {
            return null;
        }

        $mediaPath = substr($mxc, 6);
        if ($mediaPath === '') {
            return null;
        }

        return rtrim($this->matrixClientUrl, '/') . '/_matrix/media/v3/download/' . $mediaPath;
    }

    private function resolveDownloadUrl(string $url): ?string
    {
        $matrixDownload = $this->mxcToDownloadUrl($url);
        if ($matrixDownload !== null) {
            return $matrixDownload;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '/')) {
            return $url;
        }

        return null;
    }

    private function resolveUploadedFileMimeType(UploadedFile $file): string
    {
        try {
            $mime = $file->getMimeType();
            if ($mime !== null && $mime !== '') {
                return $mime;
            }
        } catch (\Throwable) {
            // Fallback when fileinfo is not available on the host.
        }

        $clientMime = $file->getClientMimeType();
        return $clientMime !== '' ? $clientMime : 'application/octet-stream';
    }
}

