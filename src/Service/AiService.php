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

    /**
     * @param array{titre:string,type?:string,duree?:string,organisme?:string,description?:string} $context
     */
    public function generateObjectives(array $context): string
    {
        try {
            $titre = trim((string) ($context['titre'] ?? ''));
            $type = trim((string) ($context['type'] ?? ''));
            $duree = trim((string) ($context['duree'] ?? ''));
            $organisme = trim((string) ($context['organisme'] ?? ''));
            $description = trim((string) ($context['description'] ?? ''));

            $prompt = "Genere 3 objectifs pedagogiques courts et concrets pour cette formation.\n"
                . "Contexte:\n"
                . "- Titre: " . ($titre !== '' ? $titre : 'N/A') . "\n"
                . "- Type: " . ($type !== '' ? $type : 'N/A') . "\n"
                . "- Duree (jours): " . ($duree !== '' ? $duree : 'N/A') . "\n"
                . "- Organisme: " . ($organisme !== '' ? $organisme : 'N/A') . "\n"
                . "- Description: " . ($description !== '' ? $description : 'N/A') . "\n\n"
                . "Contraintes de sortie:\n"
                . "- Reponds uniquement en 3 puces commencant par '•'\n"
                . "- Pas d'introduction, pas de conclusion, pas de markdown.";

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
                           'content' => $prompt,
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