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
     * @param array<string,mixed> $task
     * @param array<int,array<string,mixed>> $candidates
     * @return array<int,array{employee_id:int,score:int,reason:string}>
     */
    public function suggestTaskAssignees(array $task, array $candidates): array
    {
        if ($candidates === []) {
            return [];
        }

        try {
            $taskTitle = trim((string) ($task['title'] ?? ''));
            $taskDescription = trim((string) ($task['description'] ?? ''));
            $taskPriority = trim((string) ($task['priority'] ?? 'medium'));

            $candidatesLines = [];
            foreach ($candidates as $candidate) {
                $candidatesLines[] = sprintf(
                    '- id:%d | nom:%s | job_title:%s | active_project_tasks:%d | active_total_tasks:%d',
                    (int) ($candidate['employee_id'] ?? 0),
                    (string) ($candidate['username'] ?? 'N/A'),
                    (string) ($candidate['job_title'] ?? 'N/A'),
                    (int) ($candidate['active_project_tasks'] ?? 0),
                    (int) ($candidate['active_total_tasks'] ?? 0)
                );
            }

            $prompt = "Tu aides un RH a assigner une tache non assignee.\n"
                . "Priorite tache: " . $taskPriority . "\n"
                . "Titre tache: " . ($taskTitle !== '' ? $taskTitle : 'N/A') . "\n"
                . "Description tache: " . ($taskDescription !== '' ? $taskDescription : 'N/A') . "\n\n"
                . "Candidats disponibles:\n"
                . implode("\n", $candidatesLines)
                . "\n\n"
                . "Regles:\n"
                . "1) Favoriser l'adaptation entre tache et job_title.\n"
                . "2) Penaliser les candidats avec beaucoup de taches actives.\n"
                . "3) Retourner exactement 3 suggestions maximum.\n"
                . "4) Repondre uniquement en JSON valide sans markdown.\n"
                . "Format strict attendu:\n"
                . "{\"suggestions\":[{\"employee_id\":123,\"score\":0-100,\"reason\":\"...\"}]}";

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
                    'temperature' => 0.2,
                ],
                'timeout' => 20,
                'verify_peer' => false,
                'verify_host' => false,
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $content = $response->toArray(false);
            $raw = isset($content['choices'][0]['message']['content'])
                ? (string) $content['choices'][0]['message']['content']
                : '';

            if ($raw === '') {
                return [];
            }

            $jsonPayload = $this->extractJsonBlock($raw);
            if ($jsonPayload === null) {
                return [];
            }

            $decoded = json_decode($jsonPayload, true);
            if (!is_array($decoded) || !isset($decoded['suggestions']) || !is_array($decoded['suggestions'])) {
                return [];
            }

            $candidateIds = array_map(static fn(array $c): int => (int) ($c['employee_id'] ?? 0), $candidates);
            $candidateIds = array_values(array_filter($candidateIds, static fn(int $id): bool => $id > 0));

            $result = [];
            foreach ($decoded['suggestions'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $employeeId = (int) ($item['employee_id'] ?? 0);
                if ($employeeId <= 0 || !in_array($employeeId, $candidateIds, true)) {
                    continue;
                }

                $score = (int) ($item['score'] ?? 0);
                $score = max(0, min(100, $score));

                $reason = trim((string) ($item['reason'] ?? 'Profil adapte a la tache.'));
                if ($reason === '') {
                    $reason = 'Profil adapte a la tache.';
                }

                $result[] = [
                    'employee_id' => $employeeId,
                    'score' => $score,
                    'reason' => $reason,
                ];
            }

            return array_slice($result, 0, 3);
        } catch (\Throwable) {
            return [];
        }
    }

    private function extractJsonBlock(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $trimmed;
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $candidate = substr($trimmed, $start, $end - $start + 1);
        $candidateDecoded = json_decode($candidate, true);

        return is_array($candidateDecoded) ? $candidate : null;
    }
}