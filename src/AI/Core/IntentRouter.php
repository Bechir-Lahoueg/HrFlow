<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Domain\Enum\IntentType;
use App\AI\Infrastructure\ChatMessage;

final class IntentRouter
{
    private const GREETING_KEYWORDS = ['bonjour', 'salut', 'hello', 'hi', 'coucou', 'bjr'];
    private const QUERY_KEYWORDS = ['liste', 'donne-moi', 'montre', 'voir', 'combien', 'stats', 'statistiques', 'classement'];
    private const MUTATION_KEYWORDS = ['modifier', 'changer', 'supprimer', 'ajouter', 'créer', 'déplacer', 'promouvoir', 'rejeter'];
    private const SCHEDULE_KEYWORDS = ['planifier', ' RDV ', 'entretien', ' créneau', 'horaire', 'disponible'];
    private const REPORT_KEYWORDS = ['rapport', 'générer', 'export', ' pdf ', 'excel', 'csv'];

    public function classify(array $messages): IntentType
    {
        $lastUserMessage = $this->getLastUserMessage($messages);
        $text = \strtolower($lastUserMessage);

        if ($this->containsAny($text, self::GREETING_KEYWORDS) && \strlen($text) < 30) {
            return IntentType::GREETING;
        }

        if ($this->containsAny($text, self::MUTATION_KEYWORDS)) {
            return IntentType::MUTATION;
        }

        if ($this->containsAny($text, self::SCHEDULE_KEYWORDS)) {
            return IntentType::SCHEDULE;
        }

        if ($this->containsAny($text, self::REPORT_KEYWORDS)) {
            return IntentType::REPORT;
        }

        if ($this->containsAny($text, self::QUERY_KEYWORDS)) {
            return IntentType::DATA_QUERY;
        }

        return IntentType::DATA_QUERY;
    }

    /**
     * @param IntentType $intent
     * @param ToolRegistryInterface $registry
     * @return ToolInterface[]
     */
    public function selectTools(IntentType $intent, object $registry): array
    {
        return match ($intent) {
            IntentType::GREETING => [],
            IntentType::DATA_QUERY => $this->getToolsForQuery($registry),
            IntentType::MUTATION => $this->getToolsForMutation($registry),
            IntentType::SCHEDULE => $this->getToolsForSchedule($registry),
            IntentType::REPORT => $this->getToolsForReport($registry),
        };
    }

    /**
     * @param ChatMessage[] $messages
     */
    private function getLastUserMessage(array $messages): string
    {
        for ($i = \count($messages) - 1; $i >= 0; --$i) {
            if ($messages[$i]->role === 'user') {
                return $messages[$i]->content;
            }
        }
        return '';
    }

    private function containsAny(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (\str_contains($text, $keyword)) {
                return true;
            }
        }
        return false;
    }

    private function getToolsForQuery(object $registry): array
    {
        $names = [
            'get_candidates',
            'get_applications',
            'get_job_offers',
            'get_interviews',
            'get_pipeline_stats',
        ];
        return $this->fetchTools($registry, $names);
    }

    private function getToolsForMutation(object $registry): array
    {
        $names = [
            'move_stage',
            'delete_application',
            'update_job_offer',
            'create_job_offer',
            'bulk_move_stage',
        ];
        return $this->fetchTools($registry, $names);
    }

    private function getToolsForSchedule(object $registry): array
    {
        $names = [
            'schedule_interview',
            'get_available_slots',
            'get_interviews',
        ];
        return $this->fetchTools($registry, $names);
    }

    private function getToolsForReport(object $registry): array
    {
        $names = [
            'generate_report',
            'render_pdf',
            'render_chart',
        ];
        return $this->fetchTools($registry, $names);
    }

    private function fetchTools(object $registry, array $names): array
    {
        $tools = [];
        foreach ($names as $name) {
            try {
                $tools[] = $registry->get($name);
            } catch (\InvalidArgumentException) {
            }
        }
        return \array_slice($tools, 0, 5);
    }
}