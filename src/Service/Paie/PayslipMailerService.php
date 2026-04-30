<?php

namespace App\Service\Paie;

use App\DTO\Payroll\FichePaieResponseDTO;
use App\Repository\Rh\EmployeeRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

final class PayslipMailerService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly EmployeeRepository $employeeRepository,
        private readonly FichePaiePdfService $pdfService,
        private readonly string $senderAddress,
    ) {
    }

    /**
     * @param array<mixed> $primes
     * @param array<mixed> $deductions
     */
    public function sendPayslip(FichePaieResponseDTO $fiche, array $primes, array $deductions): void
    {
        $employee = $this->employeeRepository->find($fiche->employeeId);

        if (!$employee || !$employee->getEmail()) {
            throw new \RuntimeException('Email employee not found.');
        }

        ['fileName' => $fileName, 'content' => $content] = $this->pdfService->generatePdf($fiche, $primes, $deductions);

        $email = (new TemplatedEmail())
            ->from($this->senderAddress)
            ->to($employee->getEmail())
            ->subject(sprintf('Votre fiche de paie %02d/%d', $fiche->mois, $fiche->annee))
            ->htmlTemplate('emails/payslip.html.twig')
            ->context([
                'employeeName' => $fiche->employeeName,
                'mois' => $fiche->mois,
                'annee' => $fiche->annee,
                'salaireNet' => $fiche->salaireNet,
            ])
            ->attach($content, $fileName, 'application/pdf');

        $this->mailer->send($email);
    }
}
