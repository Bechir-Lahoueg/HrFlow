<?php

namespace App\Service\AI;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

class ReportGeneratorService
{
    private string $reportsDir;

    public function __construct(
        private Environment $twig,
        private ParameterBagInterface $params,
        string $projectDir
    ) {
        $this->reportsDir = $projectDir . '/public/uploads/ai_reports';
        if (!is_dir($this->reportsDir)) {
            mkdir($this->reportsDir, 0777, true);
        }
    }

    /**
     * Generates a PDF report and returns the relative URL.
     */
    public function generatePdf(string $template, array $data, string $filenamePrefix = 'report'): string
    {
        $html = $this->twig->render($template, $data);

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = $filenamePrefix . '_' . bin2hex(random_bytes(4)) . '.pdf';
        $filePath = $this->reportsDir . '/' . $filename;

        file_put_contents($filePath, $dompdf->output());

        return '/uploads/ai_reports/' . $filename;
    }

    /**
     * Cleans up old reports (older than 24 hours).
     */
    public function cleanupOldReports(): void
    {
        $files = glob($this->reportsDir . '/*.pdf');
        $now = time();

        foreach ($files as $file) {
            if ($now - filemtime($file) > 86400) {
                unlink($file);
            }
        }
    }
}
