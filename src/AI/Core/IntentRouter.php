<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Domain\Enum\IntentType;

final class IntentRouter
{
    public function getLabel(IntentType $intent): string
    {
        return match ($intent) {
            IntentType::GREETING => 'Salutation',
            IntentType::DATA_QUERY => 'Consultation de données',
            IntentType::PIPELINE_ANALYSIS => 'Analyse de pipeline',
            IntentType::CANDIDATE_ANALYSIS => 'Analyse de candidats',
            IntentType::MUTATION => 'Modification de données',
            IntentType::SCHEDULING => 'Planification',
            IntentType::REPORT_GENERATION => 'Génération de rapport',
            IntentType::UNKNOWN => 'Non reconnu',
        };
    }

    public function getServiceName(IntentType $intent): ?string
    {
        return match ($intent) {
            IntentType::PIPELINE_ANALYSIS => 'pipeline',
            IntentType::CANDIDATE_ANALYSIS => 'candidate',
            IntentType::DATA_QUERY => 'application',
            IntentType::MUTATION => 'application',
            IntentType::SCHEDULING => 'interview',
            IntentType::REPORT_GENERATION => 'report',
            default => null,
        };
    }

    public function requiresConfirmation(IntentType $intent): bool
    {
        return $intent === IntentType::MUTATION;
    }
}
