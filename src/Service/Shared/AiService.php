<?php

namespace App\Service\Shared;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AiService
{
    private const URL = "https://api.groq.com/openai/v1/chat/completions";
    private const MODEL = 'llama-3.1-8b-instant';
    private const VISION_MODEL = 'meta-llama/llama-4-scout-17b-16e-instruct';
    private const LEAVE_FALLBACK = 'Suggestion IA indisponible pour le moment. Redigez votre commentaire manuellement.';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(default::GROQ_API_KEY)%')]
        private readonly ?string $groqApiKey = null
    ) {}

    /**
     * @param array{titre:string,type?:string,duree?:string,organisme?:string,description?:string} $context
     */
    public function generateObjectives(array $context): string
    {
        if (!$this->isConfigured()) {
            return 'Service IA non configure (GROQ_API_KEY manquante).';
        }

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
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
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

        if (!$this->isConfigured()) {
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
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
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

        if (!$this->isConfigured()) {
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
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
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

    /**
     * @param array{leave_type?:string,start_date?:string,end_date?:string,urgency_level?:string,reason?:string} $context
     */
    public function generateEmployeeLeaveJustification(array $context): string
    {
        if (!$this->isConfigured()) {
            return self::LEAVE_FALLBACK;
        }

        $leaveType = trim((string) ($context['leave_type'] ?? 'Conge exceptionnel'));
        $startDate = trim((string) ($context['start_date'] ?? 'N/A'));
        $endDate = trim((string) ($context['end_date'] ?? 'N/A'));
        $urgency = trim((string) ($context['urgency_level'] ?? 'N/A'));
        $reason = trim((string) ($context['reason'] ?? ''));

        $prompt = "Redige une justification employee pour une demande de conge exceptionnel.\n"
            . "Contexte:\n"
            . "- Type de conge: " . ($leaveType !== '' ? $leaveType : 'Conge exceptionnel') . "\n"
            . "- Date debut: " . ($startDate !== '' ? $startDate : 'N/A') . "\n"
            . "- Date fin: " . ($endDate !== '' ? $endDate : 'N/A') . "\n"
            . "- Urgence: " . ($urgency !== '' ? $urgency : 'N/A') . "\n"
            . "- Motif initial: " . ($reason !== '' ? $reason : 'N/A') . "\n\n"
            . "Contraintes de sortie:\n"
            . "- Francais uniquement, ton neutre professionnel.\n"
            . "- Texte brut uniquement, sans puces, sans markdown, sans guillemets.\n"
            . "- Entre 40 et 90 mots.\n"
            . "- Aucune formule de politesse finale.";

        return $this->generateStrictText($prompt, self::LEAVE_FALLBACK);
    }

    /**
     * @param array{employee_name?:string,leave_type?:string,start_date?:string,end_date?:string,days_count?:int|string,urgency_level?:string,reason?:string,action?:string} $context
     */
    public function generateRhLeaveDecisionComment(array $context): string
    {
        if (!$this->isConfigured()) {
            return self::LEAVE_FALLBACK;
        }

        $action = strtoupper(trim((string) ($context['action'] ?? 'APPROVE')));
        $employee = trim((string) ($context['employee_name'] ?? 'Employe'));
        $leaveType = trim((string) ($context['leave_type'] ?? 'Conge exceptionnel'));
        $startDate = trim((string) ($context['start_date'] ?? 'N/A'));
        $endDate = trim((string) ($context['end_date'] ?? 'N/A'));
        $daysCount = trim((string) ($context['days_count'] ?? 'N/A'));
        $urgency = trim((string) ($context['urgency_level'] ?? 'N/A'));
        $reason = trim((string) ($context['reason'] ?? 'N/A'));

        $decisionLabel = $action === 'REJECT' ? 'REFUS' : 'PRE-APPROBATION RH';
        $prompt = "Redige un commentaire RH de decision pour une demande de conge exceptionnel.\n"
            . "Decision visee: " . $decisionLabel . "\n"
            . "Contexte:\n"
            . "- Employe: " . ($employee !== '' ? $employee : 'Employe') . "\n"
            . "- Type de conge: " . ($leaveType !== '' ? $leaveType : 'Conge exceptionnel') . "\n"
            . "- Date debut: " . $startDate . "\n"
            . "- Date fin: " . $endDate . "\n"
            . "- Nombre de jours: " . $daysCount . "\n"
            . "- Urgence: " . $urgency . "\n"
            . "- Motif: " . $reason . "\n\n"
            . "Contraintes de sortie:\n"
            . "- Francais uniquement, ton neutre professionnel.\n"
            . "- Texte brut uniquement, sans puces, sans markdown, sans guillemets.\n"
            . "- Entre 25 et 70 mots.\n"
            . "- Doit mentionner un motif RH explicite et factuel.";

        return $this->generateStrictText($prompt, self::LEAVE_FALLBACK);
    }

    /**
     * @param array{employee_name?:string,leave_type?:string,start_date?:string,end_date?:string,days_count?:int|string,urgency_level?:string,reason?:string,rh_comment?:string,action?:string} $context
     */
    public function generateAdminLeaveDecisionComment(array $context): string
    {
        if (!$this->isConfigured()) {
            return self::LEAVE_FALLBACK;
        }

        $action = strtoupper(trim((string) ($context['action'] ?? 'APPROVE')));
        $employee = trim((string) ($context['employee_name'] ?? 'Employe'));
        $leaveType = trim((string) ($context['leave_type'] ?? 'Conge exceptionnel'));
        $startDate = trim((string) ($context['start_date'] ?? 'N/A'));
        $endDate = trim((string) ($context['end_date'] ?? 'N/A'));
        $daysCount = trim((string) ($context['days_count'] ?? 'N/A'));
        $urgency = trim((string) ($context['urgency_level'] ?? 'N/A'));
        $reason = trim((string) ($context['reason'] ?? 'N/A'));
        $rhComment = trim((string) ($context['rh_comment'] ?? 'N/A'));

        $decisionLabel = $action === 'REJECT' ? 'REFUS DEFINITIF ADMIN' : 'APPROBATION FINALE ADMIN';
        $prompt = "Redige un commentaire Admin pour decision finale sur une demande de conge exceptionnel.\n"
            . "Decision visee: " . $decisionLabel . "\n"
            . "Contexte:\n"
            . "- Employe: " . ($employee !== '' ? $employee : 'Employe') . "\n"
            . "- Type de conge: " . ($leaveType !== '' ? $leaveType : 'Conge exceptionnel') . "\n"
            . "- Date debut: " . $startDate . "\n"
            . "- Date fin: " . $endDate . "\n"
            . "- Nombre de jours: " . $daysCount . "\n"
            . "- Urgence: " . $urgency . "\n"
            . "- Motif employe: " . $reason . "\n"
            . "- Commentaire RH: " . $rhComment . "\n\n"
            . "Contraintes de sortie:\n"
            . "- Francais uniquement, ton neutre professionnel.\n"
            . "- Texte brut uniquement, sans puces, sans markdown, sans guillemets.\n"
            . "- Entre 25 et 80 mots.\n"
            . "- Doit justifier clairement la decision finale Admin.";

        return $this->generateStrictText($prompt, self::LEAVE_FALLBACK);
    }

    public function extractMedicalCertificateTextFromImage(string $absolutePath, string $mimeType): string
    {
        if (!$this->isConfigured()) {
            return '';
        }

        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return '';
        }

        $mimeType = strtolower(trim($mimeType));
        if (!in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            return '';
        }

        $raw = file_get_contents($absolutePath);
        if ($raw === false || $raw === '') {
            return '';
        }

        $imageDataUrl = 'data:' . $mimeType . ';base64,' . base64_encode($raw);
        $prompt = "Lis ce certificat medical et extrait uniquement le texte utile de facon fidele.\n"
            . "Contraintes:\n"
            . "- Reponds en texte brut uniquement, sans markdown.\n"
            . "- Conserve les informations medicales importantes, dates, nom du medecin, duree de repos.\n"
            . "- Si un element est illisible, note [illisible].";

        try {
            $response = $this->httpClient->request('POST', self::URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::VISION_MODEL,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $prompt],
                                ['type' => 'image_url', 'image_url' => ['url' => $imageDataUrl]],
                            ],
                        ],
                    ],
                    'temperature' => 0,
                ],
                'timeout' => 30,
                'verify_peer' => false,
                'verify_host' => false,
            ]);

            if ($response->getStatusCode() !== 200) {
                return '';
            }

            $content = $response->toArray(false);
            $text = trim((string) ($content['choices'][0]['message']['content'] ?? ''));
            if ($text === '') {
                return '';
            }

            $text = preg_replace('/\s+/', ' ', $text) ?? $text;
            return trim($text, " \t\n\r\0\x0B\"'");
        } catch (\Throwable) {
            return '';
        }
    }

    public function summarizeMedicalCertificateContext(string $ocrText): string
    {
        $ocrText = trim($ocrText);
        if ($ocrText === '' || !$this->isConfigured()) {
            return '';
        }

        $prompt = "Tu es assistant RH. Resume ce certificat medical pour decision de conge exceptionnel.\n"
            . "Texte extrait:\n"
            . mb_substr($ocrText, 0, 5000)
            . "\n\nContraintes:\n"
            . "- Francais uniquement, ton neutre professionnel.\n"
            . "- 40 a 90 mots maximum.\n"
            . "- Inclure: periode probable d'arret, presence/absence de signature/cachet, niveau de lisibilite.\n"
            . "- Texte brut uniquement, sans markdown.";

        return $this->generateStrictText($prompt, 'Resume OCR indisponible pour le moment.');
    }

    private function generateStrictText(string $prompt, string $fallback): string
    {
        $content = $this->requestCompletionText($prompt, 0.4);
        if ($content === null) {
            return $fallback;
        }

        return $content !== '' ? $content : $fallback;
    }

    private function requestCompletionText(string $prompt, float $temperature = 0.7): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', self::URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => $temperature,
                ],
                'timeout' => 20,
                'verify_peer' => false,
                'verify_host' => false,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $content = $response->toArray(false);
            $text = trim((string) ($content['choices'][0]['message']['content'] ?? ''));
            if ($text === '') {
                return null;
            }

            $text = preg_replace('/\s+/', ' ', $text) ?? $text;
            return trim($text, " \t\n\r\0\x0B\"'");
        } catch (\Throwable) {
            return null;
        }
    }

    private function isConfigured(): bool
    {
        return trim((string) $this->groqApiKey) !== '';
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