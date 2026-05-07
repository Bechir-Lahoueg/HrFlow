<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Core\GeminiClient;
use App\AI\Domain\DTO\IntentDTO;
use App\AI\Domain\Enum\IntentType;

final class IntentParser
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Tu es un parseur d'intention. Analyse le message utilisateur et retourne UNIQUEMENT un JSON strict (sans texte avant ou après):

{
  "intent": "GREETING|DATA_QUERY|PIPELINE_ANALYSIS|CANDIDATE_ANALYSIS|MUTATION|SCHEDULING|REPORT_GENERATION",
  "parameters": {
    "department": "string ou null",
    "time_range": "string ou null",
    "status": "string ou null",
    "job_offer_id": "integer ou null",
    "group_by": "string ou null",
    "limit": "integer ou null",
    "action": "string ou null",
    "new_status": "string ou null"
  },
  "output_format": ["table", "chart", "insights"]
}

RÈGLES DE CLASSIFICATION:
- GREETING: bonjour, salut, hello, hi
- DATA_QUERY: liste, montre, donne, voir, combien, candidatures, offres
- PIPELINE_ANALYSIS: statistiques, pipeline, entonnoir, conversion, performance, graphique, chart
- CANDIDATE_ANALYSIS: classement, comparer, analyser candidat, meilleur profil
- MUTATION: modifier, changer, supprimer, ajouter, créer, rejeter, accepter, statut
- SCHEDULING: planifier, entretien, RDV, créneau, disponible
- REPORT_GENERATION: rapport, générer, export, PDF, Excel, CSV

Pour "output_format":
- Si le message mentionne "graphique", "chart", "visualisation" → inclure "chart"
- Si le message mentionne "tableau", "liste", "détail" → inclure "table"
- Toujours inclure "insights" pour l'analyse textuelle
PROMPT;

    public function __construct(
        private readonly GeminiClient $geminiClient,
    ) {}

    public function parse(string $message): IntentDTO
    {
        $prompt = "Message utilisateur: \"{$message}\"\n\nAnalyse et retourne le JSON:";

        $result = $this->geminiClient->chatStructured($prompt, self::SYSTEM_PROMPT);

        if (empty($result) || !isset($result['intent'])) {
            return new IntentDTO(
                intent: IntentType::DATA_QUERY,
                parameters: ['raw_message' => $message],
                outputFormat: ['table', 'insights'],
            );
        }

        $intent = IntentType::tryFrom($result['intent']) ?? IntentType::DATA_QUERY;

        return new IntentDTO(
            intent: $intent,
            parameters: $result['parameters'] ?? [],
            outputFormat: $result['output_format'] ?? ['table', 'insights'],
        );
    }
}
