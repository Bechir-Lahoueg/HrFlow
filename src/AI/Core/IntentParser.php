<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Contract\LlmClientInterface;
use App\AI\Domain\DTO\IntentDTO;
use App\AI\Domain\Enum\IntentType;
use App\AI\Infrastructure\ChatMessage;
use App\AI\Infrastructure\ChatRequest;

final class IntentParser
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Tu es un parseur d'intention. Analyse le message utilisateur et retourne UNIQUEMENT un JSON strict (sans texte avant ou après):

{
  "intent": "PIPELINE_ANALYSIS|CANDIDATE_ANALYSIS|DATA_QUERY|MUTATION|SCHEDULING|REPORT_GENERATION|GREETING|UNKNOWN",
  "parameters": {
    "department": "string ou null",
    "time_range": "string ou null (1_month, 3_months, 6_months, 1_year)",
    "date_range": "string ou null",
    "date_from": "string ou null (YYYY-MM-DD)",
    "date_to": "string ou null (YYYY-MM-DD)",
    "date": "string ou null (YYYY-MM-DD)",
    "status": "string ou null",
    "new_status": "string ou null",
    "job_offer_id": "integer ou null",
    "application_id": "integer ou null",
    "group_by": "string ou null (department, status, time, offer)",
    "limit": "integer ou null",
    "entity": "string ou null (application, interview, job_offer)",
    "type": "string ou null",
    "action": "string ou null",
    "report_type": "string ou null (pipeline, candidates, interviews, hiring_funnel)",
    "ids": "tableau d'entiers ou null"
  },
  "output_format": ["table", "chart", "insights"]
}

REGLES:
- PIPELINE_ANALYSIS: statistiques, pipeline, entonnoir, conversion, performance, graphique, chart, metrics, analyse
- CANDIDATE_ANALYSIS: classement, comparer, analyser candidat, meilleur profil, rank, compare, top candidat
- DATA_QUERY: liste, montre, donne, voir, combien, candidatures, offres, entretiens, recherche
- MUTATION: modifier, changer, supprimer, ajouter, creer, rejeter, accepter, promouvoir, statut
- SCHEDULING: planifier, entretien, RDV, creneau, disponible, interviewer, rendez-vous
- REPORT_GENERATION: rapport, generer, export, PDF, Excel, CSV, telecharger
- GREETING: bonjour, salut, hello, hi, coucou
- UNKNOWN: tout ce qui ne correspond pas aux categories ci-dessus
PROMPT;

    public function __construct(
        private readonly LlmClientInterface $llmClient,
    ) {}

    public function parse(string $message): IntentDTO
    {
        $prompt = "Message utilisateur: \"{$message}\"\n\nAnalyse et retourne le JSON:";

        $request = new ChatRequest(
            messages: [new ChatMessage('user', $prompt)],
            systemPrompt: self::SYSTEM_PROMPT,
            tools: [],
            responseMimeType: 'application/json',
        );

        $response = $this->llmClient->chat($request);
        $result = $this->extractJson($response->content);

        if ($result === null || !isset($result['intent'])) {
            return $this->fallbackIntent($message);
        }

        $intent = IntentType::tryFrom($result['intent']) ?? IntentType::UNKNOWN;

        return new IntentDTO(
            intent: $intent,
            parameters: $result['parameters'] ?? [],
            outputFormat: $result['output_format'] ?? ['insights'],
            originalMessage: $message,
        );
    }

    private function extractJson(string $content): ?array
    {
        $content = trim($content);

        if (empty($content)) {
            return null;
        }

        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function fallbackIntent(string $message): IntentDTO
    {
        $lower = mb_strtolower(trim($message));

        $greetings = ['bonjour', 'salut', 'hello', 'hi', 'coucou', 'bjr'];
        if (in_array($lower, $greetings) || strlen($lower) < 20 && count(array_filter($greetings, fn($g) => str_starts_with($lower, $g))) > 0) {
            return new IntentDTO(
                intent: IntentType::GREETING,
                parameters: [],
                outputFormat: ['insights'],
                originalMessage: $message,
            );
        }

        $patterns = [
            IntentType::PIPELINE_ANALYSIS => ['statistique', 'pipeline', 'entonnoir', 'conversion', 'performance', 'graphique', 'chart', 'metrique', 'analyse'],
            IntentType::CANDIDATE_ANALYSIS => ['classement', 'comparer', 'meilleur profil', 'top candidat', 'rank', 'score'],
            IntentType::MUTATION => ['modifier', 'changer', 'supprimer', 'ajouter', 'creer', 'rejeter', 'accepter', 'promouvoir'],
            IntentType::SCHEDULING => ['planifier', 'entretien', 'rdv', 'creneau', 'disponible', 'rendez-vous'],
            IntentType::REPORT_GENERATION => ['rapport', 'generer', 'export', 'pdf', 'excel', 'csv'],
        ];

        foreach ($patterns as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return new IntentDTO(
                        intent: $intent,
                        parameters: [],
                        outputFormat: ['table', 'insights'],
                        originalMessage: $message,
                    );
                }
            }
        }

        if (str_contains($lower, 'candidat') || str_contains($lower, 'candidature') || str_contains($lower, 'offre') || str_contains($lower, 'liste') || str_contains($lower, 'montre') || str_contains($lower, 'combien') || str_contains($lower, 'voir')) {
            return new IntentDTO(
                intent: IntentType::DATA_QUERY,
                parameters: [],
                outputFormat: ['table', 'insights'],
                originalMessage: $message,
            );
        }

        return new IntentDTO(
            intent: IntentType::UNKNOWN,
            parameters: [],
            outputFormat: ['insights'],
            originalMessage: $message,
        );
    }
}
