<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Domain\Enum\IntentType;

final class RecruitmentContextProvider
{
    private const MAX_TOKENS = 350;

    public function __construct(
        private readonly int $maxTokens = self::MAX_TOKENS,
    ) {}

    public function buildSystemPrompt(object $user, ?string $intentType = null): string
    {
        $base = <<<'PROMPT'
Tu es un assistant recrutement intelligent. Tu aides les responsables RH à gérer les candidatures, planifier des entretiens et analyser les données de recrutement.

能力:
- Consulter les candidats et leurs candidatures
- Planifier des entretiens avec les candidats
- Modifier le statut des candidatures (promotion, rejet)
- Créer et mettre à jour les offres d'emploi
- Générer des rapports et statistiques

Instructions:
- Réponds toujours en français
- Sois précis et concis
- Demande confirmation avant toute modification de données
- Utilise les outils disponibles pour récupérer les informations

PROMPT;

        $intentContext = $this->buildIntentContext($intentType);

        $full = $base . "\n\n" . $intentContext;
        if (\strlen($full) > $this->maxTokens * 4) {
            $full = \substr($full, 0, $this->maxTokens * 4);
        }

        return $full;
    }

    private function buildIntentContext(?string $intentType): string
    {
        return match ($intentType) {
            IntentType::DATA_QUERY->value => 'Contexte: L\'utilisateur demande des informations. Fais une analyse précise.',
            IntentType::MUTATION->value => 'Contexte: L\'utilisateur veut modifier des données. Demande confirmation explicite avant toute modification.',
            IntentType::SCHEDULE->value => 'Contexte: L\'utilisateur veut planifier quelque chose. Propose des créneaux disponibles.',
            IntentType::REPORT->value => 'Contexte: L\'utilisateur veut un rapport. Genère les statistiques pertinentes.',
            default => 'Contexte: Discussion générale. Réponds de manière utile et professionnelle.',
        };
    }
}