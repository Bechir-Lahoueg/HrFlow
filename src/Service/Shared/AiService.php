<?php

namespace App\Service\Shared;

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

    /**
     * @param array{titre:string,type?:string,description?:string} $context
     */
    public function generateFormationImagePromptEnglish(array $context): string
    {
        $titre = trim((string) ($context['titre'] ?? ''));
        $type = trim((string) ($context['type'] ?? ''));
        $description = trim((string) ($context['description'] ?? ''));
        $detectedTech = $this->detectFormationTechSignals($titre . ' ' . $type . ' ' . $description);
        $techHint = $detectedTech !== [] ? implode(', ', $detectedTech) : 'none';

        if ($titre === '') {
            return 'Professional corporate training banner, modern office scene, clean composition, realistic lighting, 16:9.';
        }

        try {
            $prompt = "Create one single-line image generation prompt in English for a professional training banner.\n"
                . "Context:\n"
                . "- Training title: " . $titre . "\n"
                . "- Type: " . ($type !== '' ? $type : 'N/A') . "\n"
                . "- Description: " . ($description !== '' ? $description : 'N/A') . "\n\n"
                . "Detected technologies / frameworks: " . $techHint . "\n\n"
                . "Rules:\n"
                . "- Return only the prompt text, no quotes, no markdown.\n"
                . "- Professional, modern, corporate visual style.\n"
                . "- Include realistic details and 16:9 composition.\n"
                . "- If technologies are detected, include recognizable official logo/icon elements of these technologies in the scene.\n"
                . "- Keep visuals clean: no paragraphs, no extra text overlay.";

            $response = $this->httpClient->request('POST', self::URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . self::GROQ_API_KEY,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'llama-3.1-8b-instant',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.7,
                ],
                'timeout' => 20,
                'verify_peer' => false,
                'verify_host' => false,
            ]);

            $content = $response->toArray(false);
            $text = trim((string) ($content['choices'][0]['message']['content'] ?? ''));

            if ($response->getStatusCode() === 200 && $text !== '') {
                return preg_replace('/\s+/', ' ', $text) ?? $text;
            }
        } catch (\Throwable) {
            // Fallback handled below.
        }

        if ($detectedTech !== []) {
            return sprintf(
                'Professional corporate training banner for "%s" focused on %s, modern workstation scene, include recognizable official logos/icons of %s, realistic lighting, clean composition, 16:9, no text overlay.',
                $titre,
                $type !== '' ? $type : 'software engineering',
                implode(', ', $detectedTech)
            );
        }

        return sprintf(
            'Professional corporate training banner for "%s" (%s), modern office context, realistic style, clean composition, soft blue palette, 16:9, no logo, no text overlay.',
            $titre,
            $type !== '' ? $type : 'professional training'
        );
    }

    /** @return string[] */
    private function detectFormationTechSignals(string $text): array
    {
        $haystack = mb_strtolower($text);

        $keywords = [
            'Java' => ['java', 'jdk', 'spring', 'hibernate'],
            'Symfony' => ['symfony', 'twig', 'doctrine'],
            'PHP' => ['php', 'laravel'],
            'JavaScript' => ['javascript', 'js', 'node', 'node.js', 'react', 'angular', 'vue'],
            'Python' => ['python', 'django', 'flask', 'fastapi'],
            'Docker' => ['docker', 'container', 'kubernetes', 'k8s'],
            'AWS' => ['aws', 'amazon web services'],
            'Azure' => ['azure'],
            'Git' => ['git', 'github', 'gitlab'],
            'MySQL' => ['mysql', 'sql', 'postgresql', 'postgres'],
        ];

        $detected = [];
        foreach ($keywords as $label => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    $detected[] = $label;
                    break;
                }
            }
        }

        return array_slice(array_values(array_unique($detected)), 0, 4);
    }
}