<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Contract\ToolInterface;
use App\AI\Contract\ToolRegistryInterface;
use App\AI\Domain\Enum\IntentType;
use App\AI\Infrastructure\ChatMessage;

final class IntentRouter
{
    private const GREETING_KEYWORDS = ['bonjour', 'salut', 'hello', 'hi', 'coucou', 'bjr'];
    private const QUERY_KEYWORDS = ['liste', 'donne-moi', 'montre', 'voir', 'combien', 'stats', 'statistiques', 'classement'];
    private const MUTATION_KEYWORDS = ['modifier', 'changer', 'supprimer', 'ajouter', 'créer', 'déplacer', 'promouvoir', 'rejeter'];
    private const SCHEDULE_KEYWORDS = ['planifier', ' RDV ', 'entretien', ' créneau', 'horaire', 'disponible'];
    private const REPORT_KEYWORDS = ['rapport', 'générer', 'export', ' pdf ', 'excel', 'csv'];

    private const JOB_OFFER_KEYWORDS = ['offre', 'emploi', 'poste', 'recrutement', 'job', 'recrute'];
    private const APPLICATION_KEYWORDS = ['candidat', 'candidature', 'cv', 'postulant', 'candidats'];
    private const INTERVIEW_KEYWORDS = ['entretien', 'rendez-vous', 'interview', 'rdv'];

    /**
     * @param ChatMessage[] $messages
     * @return IntentType[]
     */
    public function classify(array $messages): array
    {
        $lastUserMessage = $this->getLastUserMessage($messages);
        $text = \strtolower($lastUserMessage);

        if ($this->containsAny($text, self::GREETING_KEYWORDS) && \strlen($text) < 30) {
            return [IntentType::GREETING];
        }

        $intents = [];

        if ($this->containsAny($text, self::MUTATION_KEYWORDS)) {
            $intents[] = IntentType::MUTATION;
        }

        if ($this->containsAny($text, self::SCHEDULE_KEYWORDS)) {
            $intents[] = IntentType::SCHEDULE;
        }

        if ($this->containsAny($text, self::REPORT_KEYWORDS)) {
            $intents[] = IntentType::REPORT;
        }

        if ($this->containsAny($text, self::QUERY_KEYWORDS) || empty($intents)) {
            $intents[] = IntentType::DATA_QUERY;
        }

        return \array_unique($intents);
    }

    /**
     * @param ChatMessage[] $messages
     * @return string[]
     */
    public function detectEntities(array $messages): array
    {
        $lastUserMessage = $this->getLastUserMessage($messages);
        $text = \strtolower($lastUserMessage);

        $entities = [];

        if ($this->containsAny($text, self::JOB_OFFER_KEYWORDS)) {
            $entities[] = 'job_offer';
        }

        if ($this->containsAny($text, self::APPLICATION_KEYWORDS)) {
            $entities[] = 'application';
        }

        if ($this->containsAny($text, self::INTERVIEW_KEYWORDS)) {
            $entities[] = 'interview';
        }

        return $entities;
    }

    /**
     * @param IntentType[] $intents
     * @param string[] $entities
     * @return ToolInterface[]
     */
    public function selectTools(array $intents, object $registry, array $entities = []): array
    {
        $toolNames = [];

        foreach ($intents as $intent) {
            $names = match ($intent) {
                IntentType::DATA_QUERY => $this->getQueryToolNames(),
                IntentType::MUTATION => $this->getMutationToolNames(),
                IntentType::SCHEDULE => $this->getScheduleToolNames(),
                IntentType::REPORT => $this->getReportToolNames(),
                IntentType::GREETING => [],
            };
            $toolNames = \array_merge($toolNames, $names);
        }

        if (!empty($entities)) {
            $entityTools = $this->getEntityToolNames($entities);
            $toolNames = \array_merge($toolNames, $entityTools);
        }

        $toolNames = \array_unique(\array_values($toolNames));

        return $this->fetchTools($registry, \array_slice($toolNames, 0, 5));
    }

    /**
     * @return string[]
     */
    private function getQueryToolNames(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    private function getMutationToolNames(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    private function getScheduleToolNames(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    private function getReportToolNames(): array
    {
        return [
            'generate_report',
            'render_pdf',
            'render_chart',
        ];
    }

    /**
     * @param string[] $entities
     * @return string[]
     */
    private function getEntityToolNames(array $entities): array
    {
        $map = [
            'job_offer' => 'manage_job_offers',
            'application' => 'manage_applications',
            'interview' => 'manage_interviews',
        ];

        $names = [];
        foreach ($entities as $entity) {
            if (isset($map[$entity])) {
                $names[] = $map[$entity];
            }
        }

        return $names;
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

    /**
     * @param string[] $keywords
     */
    private function containsAny(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (\str_contains($text, $keyword)) {
                return true;
            }
        }
        return false;
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
