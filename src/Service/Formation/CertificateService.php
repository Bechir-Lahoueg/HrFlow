<?php

namespace App\Service\Formation;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;



class CertificateService
{
    public function __construct(
        private readonly Environment $twig,
        private readonly ParameterBagInterface $params,
        private readonly QrCodeService $qrCodeService
    ) {
    }

    /**
     * @return array{fileName:string, content:string}
     */
    public function generateCertificate(
        string $employeeName,
        string $formationTitle,
        \DateTimeInterface $dateDebut,
        \DateTimeInterface $dateFin,
        string $organisme,
        ?string $rhCreatorName = null,
        ?string $token = null
    ): array {
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($pdfOptions);

        $templateBackground = $this->buildTemplateBackgroundDataUri();


        $qrCodeDataUri = null;
        if ($token) {
            $qrCodeDataUri = $this->qrCodeService->generateQrCode($token);
        }


        $html = $this->twig->render('DashboardEmployee/formation/certificate/template.html.twig', [
            'name' => $employeeName,
            'formation' => $formationTitle,
            'debut' => $dateDebut,
            'fin' => $dateFin,
            'org' => $organisme,
            'today' => new \DateTime(),
            'template_background' => $templateBackground,
            'rh_creator_name' => $rhCreatorName,
            'qr_code' => $qrCodeDataUri,
            'token' => $token,
        ]);

        if (function_exists('mb_convert_encoding')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        }

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $employeeName) ?: 'Employe';
        $fileName = 'Certificat_' . $safeName . '.pdf';

        return [
            'fileName' => $fileName,
            'content' => $dompdf->output(),
        ];
    }

    private function buildTemplateBackgroundDataUri(): ?string
    {
        $projectDir = (string) $this->params->get('kernel.project_dir');
        $templatePath = $projectDir . '/public/images/certificate_template.png';

        if (!is_file($templatePath) || !is_readable($templatePath)) {
            return null;
        }

        $content = file_get_contents($templatePath);
        if ($content === false) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($content);
    }
}