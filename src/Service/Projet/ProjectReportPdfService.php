<?php

namespace App\Service\Projet;

use Knp\Snappy\Pdf;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

final class ProjectReportPdfService
{
    public function __construct(
        private readonly Environment $twig,
        private readonly Pdf $pdf,
        private readonly ParameterBagInterface $parameterBag,
    ) {}

    /**
     * @param array<string,mixed> $context
     * @return array{fileName:string,content:string}
     */
    public function generatePdf(array $context): array
    {
        $context['logoDataUri'] = $this->resolveLogoDataUri();

        $html = $this->twig->render('DashboardHr/Project/report_pdf.html.twig', $context);
        $content = $this->pdf->getOutputFromHtml($html);

        return [
            'fileName' => 'Rapport_Projets_RH_' . date('Ymd_His') . '.pdf',
            'content' => (string) $content,
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

