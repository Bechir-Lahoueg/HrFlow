<?php

namespace App\Service\Shared;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HuggingFaceEmotionService
{
    private const DEFAULT_LABEL = 'unknown';

    private ?string $lastError = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiToken,
        private readonly string $model,
    ) {
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function isConfigured(): bool
    {
        return $this->apiToken !== '' && $this->model !== '';
    }

    /**
     * @return array{label:string,score:float}
     */
    public function analyze(string $text): array
    {
        $this->lastError = null;

        $input = trim($text);
        if ($input === '' || !$this->isConfigured()) {
            return ['label' => self::DEFAULT_LABEL, 'score' => 0.0];
        }

        try {
            $response = $this->httpClient->request(
                'POST',
                // Le nom de modele HF contient un slash (owner/model), il ne faut pas encoder tout le segment.
                'https://api-inference.huggingface.co/models/' . ltrim($this->model, '/'),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiToken,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'inputs' => $input,
                        'options' => [
                            'wait_for_model' => true,
                        ],
                    ],
                    'timeout' => 20,
                ]
            );

            $status = $response->getStatusCode();
            $rawBody = $response->getContent(false);
            $decoded = json_decode($rawBody, true);

            if ($status < 200 || $status >= 300 || !is_array($decoded)) {
                $errorText = is_array($decoded) ? (string) ($decoded['error'] ?? '') : '';
                $this->lastError = 'HF HTTP ' . $status . ($errorText !== '' ? ' - ' . $errorText : '');
                return ['label' => self::DEFAULT_LABEL, 'score' => 0.0];
            }

            if (isset($decoded['error']) && is_string($decoded['error'])) {
                $this->lastError = 'HF API - ' . $decoded['error'];
                return ['label' => self::DEFAULT_LABEL, 'score' => 0.0];
            }

            // API retourne souvent [[{label, score}, ...]]
            $predictions = $decoded;
            if (isset($decoded[0]) && is_array($decoded[0]) && isset($decoded[0][0]) && is_array($decoded[0][0])) {
                $predictions = $decoded[0];
            }

            if (!is_array($predictions) || $predictions === []) {
                return ['label' => self::DEFAULT_LABEL, 'score' => 0.0];
            }

            $bestLabel = self::DEFAULT_LABEL;
            $bestScore = 0.0;

            foreach ($predictions as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $label = strtolower(trim((string) ($item['label'] ?? '')));
                $score = (float) ($item['score'] ?? 0.0);

                if ($label === '' || $score <= $bestScore) {
                    continue;
                }

                $bestLabel = $label;
                $bestScore = $score;
            }

            return ['label' => $bestLabel, 'score' => round($bestScore, 4)];
        } catch (\Throwable) {
            $this->lastError = 'Exception locale pendant analyse emotion HF.';
            return ['label' => self::DEFAULT_LABEL, 'score' => 0.0];
        }
    }
}


