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

    /**
     * @param string[] $entities
     */
    public function buildSystemPrompt(object $user, ?string $intentType = null, array $entities = []): string
    {
        $base = <<<'PROMPT'
Tu es un assistant recrutement intelligent. Tu aides les responsables RH à gérer les candidatures, planifier des entretiens et analyser les données de recrutement.

Capacités:
- Consulter les candidats et leurs candidatures
- Planifier des entretiens avec les candidats
- Modifier le statut des candidatures (promotion, rejet)
- Créer et mettre à jour les offres d'emploi
- Générer des rapports et statistiques

Instructions:
- Réponds toujours en français
- Sois précis et concis
- Demande confirmation explicite avant toute modification de données
- Utilise les outils disponibles pour récupérer les informations
- Pour effectuer des actions sur un enregistrement spécifique, commence par interroger le gestionnaire pour récupérer l'ID unique, puis passe cet ID dans l'appel suivant

PROMPT;

        $parts = [$base];
        $parts[] = $this->buildIntentContext($intentType);
        $parts[] = $this->buildEntityContext($entities);

        $full = \implode("\n\n", \array_filter($parts));

        if (\strlen($full) > $this->maxTokens * 4) {
            $full = \substr($full, 0, $this->maxTokens * 4);
        }

        return $full;
    }

    private function buildIntentContext(?string $intentType): string
    {
        return match ($intentType) {
            IntentType::DATA_QUERY->value => 'Contexte: L\'utilisateur demande des informations. Utilise les outils de consultation disponibles.',
            IntentType::MUTATION->value => 'Contexte: L\'utilisateur veut modifier des données. Demande confirmation explicite avant toute modification.',
            IntentType::SCHEDULE->value => 'Contexte: L\'utilisateur veut planifier quelque chose. Vérifie les informations disponibles avant de proposer.',
            IntentType::REPORT->value => 'Contexte: L\'utilisateur veut un rapport. Génère les statistiques pertinentes.',
            default => 'Contexte: Discussion générale. Réponds de manière utile et professionnelle.',
        };
    }

    /**
     * @param string[] $entities
     */
    private function buildEntityContext(array $entities): string
    {
        if (empty($entities)) {
            return '';
        }

        $labels = [
            'job_offer' => 'offres d\'emploi (manage_job_offers: liste, détail, création, modification, changement de statut, suppression)',
            'application' => 'candidatures (manage_applications: liste, détail, changement de statut, classement, création, suppression)',
            'interview' => 'entretiens (manage_interviews: liste, détail, planification, modification, annulation, suppression)',
        ];

        $available = [];
        foreach ($entities as $entity) {
            if (isset($labels[$entity])) {
                $available[] = $labels[$entity];
            }
        }

        if (empty($available)) {
            return '';
        }

        return 'Entités détectées: ' . \implode(', ', $available) . '. Utilise le gestionnaire approprié pour chaque opération.';
    }
}
