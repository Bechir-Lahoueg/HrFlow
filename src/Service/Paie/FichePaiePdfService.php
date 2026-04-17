<?php

namespace App\Service\Paie;

use App\DTO\Payroll\FichePaieResponseDTO;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class FichePaiePdfService
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    /**
     * @param array<mixed> $primes
     * @param array<mixed> $deductions
     * @return array{fileName: string, content: string}
     */
    public function generatePdf(
        FichePaieResponseDTO $fiche,
        array $primes,
        array $deductions,
    ): array {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);

        $html = $this->twig->render('DashboardEmployee/payroll/fiche_paie_pdf.html.twig', [
            'fiche'      => $fiche,
            'primes'     => $primes,
            'deductions' => $deductions,
        ]);

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $months = [
            1 => 'Janvier', 2 => 'Fevrier', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Aout',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Decembre',
        ];

        $monthLabel = $months[$fiche->mois] ?? (string) $fiche->mois;
        $safeName   = preg_replace('/[^A-Za-z0-9_-]/', '_', $fiche->employeeName) ?: 'Employe';
        $fileName   = sprintf('Bulletin_%s_%s_%d.pdf', $safeName, $monthLabel, $fiche->annee);

        return [
            'fileName' => $fileName,
            'content'  => (string) $dompdf->output(),
        ];
    }
}
