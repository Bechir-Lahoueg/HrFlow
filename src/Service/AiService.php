<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AiService
{
    private const GROQ_API_KEY = "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";
    private const URL = "https://api.groq.com/openai/v1/chat/completions";

    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {}

    public function generateObjectives(string $title): string
    {
        try {
            $response = $this->httpClient->request('POST', self::URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . self::GROQ_API_KEY,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'llama-3.1-8b-instant',
                    'messages' => [
                        [
                            'role' => 'user',
                           'content' => "Génère 3 objectifs courts pour la formation : " . $title . ".
                                                 Réponds UNIQUEMENT sous forme de liste à puces (•).
                                                 PAS de phrase d'introduction, PAS de conclusion, PAS de gras (**)."
                        ]
                    ],
                    'temperature' => 0.7,
                ],
                'timeout' => 20,
                'verify_peer' => false,
                'verify_host' => false,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->toArray(false); // throw=false to handle errors manually

            if ($statusCode !== 200) {
                return "Erreur technique (" . $statusCode . ") : " . json_encode($content);
            }

            if (isset($content['choices'][0]['message']['content'])) {
                return trim($content['choices'][0]['message']['content']);
            }

            return "Aucun contenu généré. " . json_encode($content);

        } catch (\Throwable $e) {
            return "Erreur locale : " . $e->getMessage();
        }
    }
}