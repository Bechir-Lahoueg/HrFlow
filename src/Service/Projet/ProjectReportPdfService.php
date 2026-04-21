<?php

namespace App\Service\Projet;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

final class ProjectReportPdfService
{
    public function __construct(
        private readonly Environment $twig,
        private readonly ParameterBagInterface $parameterBag,
    ) {}

    /**
     * @param array<string,mixed> $context
     * @return array{fileName:string,content:string}
     */
    public function generatePdf(array $context): array
    {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);

        $context['logoDataUri'] = $this->resolveLogoDataUri();

        $html = $this->twig->render('DashboardHr/Project/report_pdf.html.twig', $context);

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return [
            'fileName' => 'Rapport_Projets_RH_' . date('Ymd_His') . '.pdf',
            'content' => (string) $dompdf->output(),
        ];
    }

    private function resolveLogoDataUri(): ?string
    {
        $projectDir = (string) $this->parameterBag->get('kernel.project_dir');
        $logoPath = $projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'logo.png';

        if (!is_file($logoPath) || !is_readable($logoPath)) {
            return null;
        }

        $content = @file_get_contents($logoPath);
        if (!is_string($content) || $content === '') {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($content);
    }
}

