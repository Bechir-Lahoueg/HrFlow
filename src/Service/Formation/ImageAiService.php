<?php

namespace App\Service\Formation;

use App\Service\Shared\AiService;

final class ImageAiService
{
    private const BASE_URL = 'https://image.pollinations.ai/prompt/';

    public function __construct(
        private readonly AiService $aiService,
    ) {
    }

    /**
     * Génère une URL d'image professionnelle basée sur le titre et le type
     */
    public function generateFormationBanner(string $titre, string $type, ?string $description = null): string
    {
        $logoUrl = $this->resolveTechnologyLogoUrl($titre, $type, $description);
        if ($logoUrl !== null) {
            return $logoUrl;
        }

        $prompt = $this->aiService->generateFormationImagePromptEnglish([
            'titre' => $titre,
            'type' => $type,
            'description' => (string) ($description ?? ''),
        ]);

        $seed = random_int(1, 99999); // Pour avoir une image unique a chaque fois
        $suffix = "?width=1024&height=576&nologo=true&seed={$seed}";

        // Keep URL short enough for the current DB schema (formation.image VARCHAR(255)).
        $maxPromptLength = max(32, 255 - strlen(self::BASE_URL) - strlen($suffix));
        $encodedPrompt = rawurlencode(trim($prompt));
        if (strlen($encodedPrompt) > $maxPromptLength) {
            $encodedPrompt = substr($encodedPrompt, 0, $maxPromptLength);
            $encodedPrompt = rtrim($encodedPrompt, '%');
            $encodedPrompt = preg_replace('/%[0-9A-F]?$/', '', $encodedPrompt) ?? $encodedPrompt;
        }

        return self::BASE_URL . $encodedPrompt . $suffix;
    }

    private function resolveTechnologyLogoUrl(string $titre, string $type, ?string $description = null): ?string
    {
        $text = mb_strtolower(trim($titre . ' ' . $type . ' ' . (string) $description));

        $logos = [
            'angular' => 'https://cdn.simpleicons.org/angular/DD0031',
            'symfony' => 'https://cdn.simpleicons.org/symfony/000000',
            'php' => 'https://cdn.simpleicons.org/php/777BB4',
            'java' => 'https://cdn.simpleicons.org/openjdk/EA2D2E',
            'spring' => 'https://cdn.simpleicons.org/spring/6DB33F',
            'javascript' => 'https://cdn.simpleicons.org/javascript/F7DF1E',
            'typescript' => 'https://cdn.simpleicons.org/typescript/3178C6',
            'node' => 'https://cdn.simpleicons.org/nodedotjs/339933',
            'react' => 'https://cdn.simpleicons.org/react/61DAFB',
            'vue' => 'https://cdn.simpleicons.org/vuedotjs/4FC08D',
            'python' => 'https://cdn.simpleicons.org/python/3776AB',
            'django' => 'https://cdn.simpleicons.org/django/092E20',
            'mysql' => 'https://cdn.simpleicons.org/mysql/4479A1',
            'postgres' => 'https://cdn.simpleicons.org/postgresql/4169E1',
            'docker' => 'https://cdn.simpleicons.org/docker/2496ED',
            'kubernetes' => 'https://cdn.simpleicons.org/kubernetes/326CE5',
            'aws' => 'https://cdn.simpleicons.org/amazonaws/232F3E',
            'azure' => 'https://cdn.simpleicons.org/microsoftazure/0078D4',
            'git' => 'https://cdn.simpleicons.org/git/F05032',
            'github' => 'https://cdn.simpleicons.org/github/181717',
        ];

        foreach ($logos as $keyword => $url) {
            if (str_contains($text, $keyword)) {
                return $url;
            }
        }

        return null;
    }
}