<?php

declare(strict_types=1);

namespace App\AI\Tool\Reporting;

use App\AI\Contract\ToolInterface;
use App\AI\Domain\ValueObject\ToolOutput;
use Doctrine\ORM\EntityManagerInterface;

final class ExportPdfTool implements ToolInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function getName(): string
    {
        return 'export_pdf';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'export_pdf',
            'description' => 'Exporte des données (candidats, offres, entretiens, rapports) en PDF. Spécifiez le type de données et les filtres.',
            'parameters' => [
                'type' => [
                    'type' => 'string',
                    'description' => 'Type de données à exporter: candidates, job_offers, interviews, pipeline_report, performance_report',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Titre personnalisé du PDF',
                ],
                'job_offer_id' => ['type' => 'integer', 'description' => 'ID de l\'offre (pour filtrer par offre)'],
                'status' => ['type' => 'string', 'description' => 'Filtrer par statut'],
                'from_date' => ['type' => 'string', 'description' => 'Date de début (YYYY-MM-DD)'],
                'to_date' => ['type' => 'string', 'description' => 'Date de fin (YYYY-MM-DD)'],
            ],
            'required' => ['type'],
        ];
    }

    public function execute(array $args, object $user): ToolOutput
    {
        $type = $args['type'] ?? 'candidates';
        $title = $args['title'] ?? null;

        $validTypes = ['candidates', 'job_offers', 'interviews', 'pipeline_report', 'performance_report'];
        if (!in_array($type, $validTypes)) {
            return new ToolOutput(
                llmSummary: "Type invalide: {$type}. Types possibles: " . implode(', ', $validTypes),
            );
        }

        $data = match ($type) {
            'candidates' => $this->getCandidates($args),
            'job_offers' => $this->getJobOffers($args),
            'interviews' => $this->getInterviews($args),
            'pipeline_report' => $this->getPipelineReport($args),
            'performance_report' => $this->getPerformanceReport($args),
        };

        $rowCount = is_countable($data) ? count($data) : 0;

        $pdfPath = $this->generatePdf($type, $data, $title, $args);

        $summary = sprintf(
            "PDF exporté: %s (%d élément(s)). Fichier: %s",
            ucfirst(str_replace('_', ' ', $type)),
            $rowCount,
            $pdfPath,
        );

        return new ToolOutput(
            llmSummary: $summary,
            uiPayload: [
                'type' => 'pdf_export',
                'export_type' => $type,
                'file_path' => $pdfPath,
                'file_name' => basename($pdfPath),
                'row_count' => $rowCount,
                'title' => $title ?? $this->getDefaultTitle($type),
            ],
        );
    }

    private function getCandidates(array $args): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('a')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->where('a.isDeleted = :deleted')
            ->setParameter('deleted', false);

        if (isset($args['job_offer_id'])) {
            $qb->andWhere('a.jobOffer = :jobId')
                ->setParameter('jobId', $args['job_offer_id']);
        }
        if (isset($args['status'])) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $args['status']);
        }

        $qb->orderBy('a.appliedAt', 'DESC')->setMaxResults(100);
        $applications = $qb->getQuery()->getResult();

        $data = [];
        foreach ($applications as $app) {
            $data[] = [
                'candidate_name' => $app->getCandidateName(),
                'email' => $app->getEmailAddress(),
                'job_title' => $app->getJobOffer()?->getTitle() ?? 'N/A',
                'department' => $app->getJobOffer()?->getDepartment() ?? 'N/A',
                'status' => $app->getStatus(),
                'status_label' => $app->getStatusLabel(),
                'applied_at' => $app->getAppliedAt()?->format('d/m/Y H:i') ?? 'N/A',
            ];
        }
        return $data;
    }

    private function getJobOffers(array $args): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('j')
            ->from(\App\Entity\Recrutement\JobOffer::class, 'j')
            ->where('j.isDeleted = :deleted')
            ->setParameter('deleted', false);

        if (isset($args['status'])) {
            $qb->andWhere('j.status = :status')
                ->setParameter('status', $args['status']);
        }

        $qb->orderBy('j.createdAt', 'DESC')->setMaxResults(100);
        $offers = $qb->getQuery()->getResult();

        $data = [];
        foreach ($offers as $offer) {
            $data[] = [
                'title' => $offer->getTitle(),
                'department' => $offer->getDepartment(),
                'location' => $offer->getLocation(),
                'employment_type' => $offer->getEmploymentType(),
                'status' => $offer->getStatus(),
                'created_at' => $offer->getCreatedAt()?->format('d/m/Y') ?? 'N/A',
            ];
        }
        return $data;
    }

    private function getInterviews(array $args): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('i')
            ->from(\App\Entity\Recrutement\Interview::class, 'i')
            ->join('i.application', 'a');

        if (isset($args['from_date'])) {
            $qb->andWhere('i.interviewDate >= :fromDate')
                ->setParameter('fromDate', new \DateTime($args['from_date']));
        }
        if (isset($args['to_date'])) {
            $qb->andWhere('i.interviewDate <= :toDate')
                ->setParameter('toDate', new \DateTime($args['to_date']));
        }

        $qb->orderBy('i.interviewDate', 'ASC')->setMaxResults(100);
        $interviews = $qb->getQuery()->getResult();

        $data = [];
        foreach ($interviews as $interview) {
            $data[] = [
                'candidate_name' => $interview->getApplication()?->getCandidateName() ?? 'N/A',
                'job_title' => $interview->getApplication()?->getJobOffer()?->getTitle() ?? 'N/A',
                'type' => $interview->getType() ?? 'N/A',
                'interview_date' => $interview->getInterviewDate()?->format('d/m/Y H:i') ?? 'N/A',
                'result' => $interview->getResult() ?? 'En attente',
                'score' => $interview->getScore() !== null ? $interview->getScore() . '/100' : 'N/A',
            ];
        }
        return $data;
    }

    private function getPipelineReport(array $args): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('a.status', 'COUNT(a.id) as cnt')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->where('a.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->groupBy('a.status');

        if (isset($args['job_offer_id'])) {
            $qb->andWhere('a.jobOffer = :jobId')->setParameter('jobId', $args['job_offer_id']);
        }

        $results = $qb->getQuery()->getResult();
        $stats = [];
        $total = 0;
        foreach ($results as $row) {
            $stats[$row['status']] = (int) $row['cnt'];
            $total += (int) $row['cnt'];
        }

        return [
            'total' => $total,
            'by_status' => $stats,
            'pipeline' => [
                'PENDING' => $stats['PENDING'] ?? 0,
                'REVIEWING' => $stats['REVIEWING'] ?? 0,
                'INTERVIEW' => $stats['INTERVIEW'] ?? 0,
                'OFFER' => $stats['OFFER'] ?? 0,
                'HIRED' => $stats['HIRED'] ?? 0,
                'REJECTED' => $stats['REJECTED'] ?? 0,
            ],
        ];
    }

    private function getPerformanceReport(array $args): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('a.status', 'COUNT(a.id) as cnt')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->where('a.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->groupBy('a.status');

        $results = $qb->getQuery()->getResult();
        $stats = [];
        $total = 0;
        foreach ($results as $row) {
            $stats[$row['status']] = (int) $row['cnt'];
            $total += (int) $row['cnt'];
        }

        $hired = $stats['HIRED'] ?? 0;
        $rejected = $stats['REJECTED'] ?? 0;

        return [
            'total_applications' => $total,
            'hired' => $hired,
            'rejected' => $rejected,
            'conversion_rate' => $total > 0 ? round(($hired / $total) * 100, 1) : 0,
            'by_status' => $stats,
        ];
    }

    private function generatePdf(string $type, array $data, ?string $customTitle, array $args): string
    {
        $projectDir = $this->em->getConnection()->getParams()['path'] ?? '';
        if (!$projectDir) {
            $projectDir = $_SERVER['PROJECT_DIR'] ?? '/tmp';
        }

        $uploadDir = $projectDir . '/public/uploads/reports';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $fileName = sprintf(
            '%s_%s_%s.pdf',
            $type,
            date('Ymd_His'),
            bin2hex(random_bytes(4))
        );

        $filePath = $uploadDir . '/' . $fileName;

        $title = $customTitle ?? $this->getDefaultTitle($type);
        $generatedAt = date('d/m/Y H:i');

        $html = $this->buildPdfHtml($type, $data, $title, $generatedAt);

        file_put_contents($filePath, $html);

        return '/uploads/reports/' . $fileName;
    }

    private function buildPdfHtml(string $type, array $data, string $title, string $generatedAt): string
    {
        $headers = match ($type) {
            'candidates' => ['Candidat', 'Email', 'Offre', 'Département', 'Statut', 'Date'],
            'job_offers' => ['Titre', 'Département', 'Lieu', 'Type', 'Statut', 'Créée le'],
            'interviews' => ['Candidat', 'Offre', 'Type', 'Date', 'Résultat', 'Score'],
            default => [],
        };

        $columns = match ($type) {
            'candidates' => ['candidate_name', 'email', 'job_title', 'department', 'status_label', 'applied_at'],
            'job_offers' => ['title', 'department', 'location', 'employment_type', 'status', 'created_at'],
            'interviews' => ['candidate_name', 'job_title', 'type', 'interview_date', 'result', 'score'],
            default => [],
        };

        $rows = '';
        foreach ($data as $row) {
            $cells = '';
            foreach ($columns as $col) {
                $val = $row[$col] ?? 'N/A';
                $cells .= sprintf('<td class="col-%s">%s</td>', $col, htmlspecialchars($val));
            }
            $rows .= "<tr>{$cells}</tr>\n";
        }

        $th = '';
        foreach ($headers as $h) {
            $th .= "<th>{$h}</th>";
        }

        $content = match ($type) {
            'pipeline_report', 'performance_report' => $this->buildReportHtml($type, $data),
            default => $this->buildTableHtml($th, $rows),
        };

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 40px; color: #1a1a2e; }
        .header { border-bottom: 3px solid #14b8a6; padding-bottom: 15px; margin-bottom: 30px; }
        .title { font-size: 24px; font-weight: 700; color: #0f172a; }
        .subtitle { font-size: 12px; color: #64748b; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 11px; }
        th { background: #0f172a; color: #fff; padding: 8px; text-align: left; font-size: 10px; }
        td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) { background: #f8fafc; }
        .footer { margin-top: 30px; font-size: 10px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 10px; }
        .stat-box { display: inline-block; margin: 10px; padding: 15px 25px; background: #f0fdfa; border: 1px solid #14b8a6; border-radius: 8px; text-align: center; }
        .stat-value { font-size: 28px; font-weight: 700; color: #0d9488; }
        .stat-label { font-size: 10px; color: #64748b; text-transform: uppercase; }
        .pipeline-bar { display: flex; height: 30px; border-radius: 6px; overflow: hidden; margin: 20px 0; }
        .pipeline-segment { display: flex; align-items: center; justify-content: center; color: #fff; font-size: 10px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">HrFlow — {$title}</div>
        <div class="subtitle">Généré le {$generatedAt}</div>
    </div>
    {$content}
    <div class="footer">HrFlow • Rapport généré automatiquement par l'assistant IA</div>
</body>
</html>
HTML;
    }

    private function buildTableHtml(string $th, string $rows): string
    {
        return "<table><thead><tr>{$th}</tr></thead><tbody>{$rows}</tbody></table>";
    }

    private function buildReportHtml(string $type, array $data): string
    {
        if ($type === 'pipeline_report') {
            $total = $data['total'] ?? 0;
            $pipeline = $data['pipeline'] ?? [];
            $colors = [
                'PENDING' => '#f59e0b',
                'REVIEWING' => '#3b82f6',
                'INTERVIEW' => '#8b5cf6',
                'OFFER' => '#10b981',
                'HIRED' => '#059669',
                'REJECTED' => '#ef4444',
            ];

            $statsHtml = '';
            foreach ($pipeline as $status => $count) {
                $pct = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                $color = $colors[$status] ?? '#94a3b8';
                $statsHtml .= sprintf(
                    '<div class="stat-box"><div class="stat-value" style="color:%s">%d</div><div class="stat-label">%s (%.1f%%)</div></div>',
                    $color,
                    $count,
                    ucfirst(strtolower($status)),
                    $pct,
                );
            }

            $barHtml = '';
            foreach ($pipeline as $status => $count) {
                $pct = $total > 0 ? ($count / $total) * 100 : 0;
                $color = $colors[$status] ?? '#94a3b8';
                if ($pct > 2) {
                    $barHtml .= sprintf(
                        '<div class="pipeline-segment" style="width:%.1f%%;background:%s">%d</div>',
                        $pct,
                        $color,
                        $count,
                    );
                } else {
                    $barHtml .= sprintf(
                        '<div class="pipeline-segment" style="width:%.1f%%;background:%s"></div>',
                        $pct,
                        $color,
                    );
                }
            }

            return sprintf(
                '<div><p><strong>Total candidatures:</strong> %d</p><div class="pipeline-bar">%s</div>%s</div>',
                $total,
                $barHtml,
                $statsHtml,
            );
        }

        $rate = $data['conversion_rate'] ?? 0;
        return sprintf(
            '<div class="stat-box"><div class="stat-value">%.1f%%</div><div class="stat-label">Taux de conversion</div></div>' .
            '<div class="stat-box"><div class="stat-value">%d</div><div class="stat-label">Total candidatures</div></div>' .
            '<div class="stat-box"><div class="stat-value">%d</div><div class="stat-label">Recrutés</div></div>' .
            '<div class="stat-box"><div class="stat-value">%d</div><div class="stat-label">Refusés</div></div>',
            $rate,
            $data['total_applications'] ?? 0,
            $data['hired'] ?? 0,
            $data['rejected'] ?? 0,
        );
    }

    private function getDefaultTitle(string $type): string
    {
        return match ($type) {
            'candidates' => 'Liste des Candidats',
            'job_offers' => 'Liste des Offres d\'Emploi',
            'interviews' => 'Liste des Entretiens',
            'pipeline_report' => 'Rapport Pipeline',
            'performance_report' => 'Rapport Performance',
            default => 'Rapport HrFlow',
        };
    }
}
