<?php

namespace App\DTO\Payroll;

use App\Entity\Paie\FichePaie;

/**
 * FichePaie Response DTO - Data transfer object for returning pay slip data
 */
class FichePaieResponseDTO
{
    public int $id;
    public int $employeeId;
    public string $employeeName;
    public int $mois;
    public int $annee;
    public string $salaireBrut;
    public string $totalPrimes;
    public string $totalDeductions;
    public string $salaireNet;
    public bool $statutPaiement = false;
    public ?string $notes;
    public ?\DateTimeInterface $createdAt;
    public ?\DateTimeInterface $updatedAt;

    public function __construct(FichePaie $fichePaie)
    {
        $this->id = $fichePaie->getId() ?? 0;
        $employee = $fichePaie->getEmployee();
        $this->employeeId = $employee?->getId() ?? 0;
        $this->employeeName = ($employee?->getFirstName() ?? '') . ' ' . ($employee?->getLastName() ?? '');
        $this->mois = $fichePaie->getMois() ?? 0;
        $this->annee = $fichePaie->getAnnee() ?? 0;
        $this->salaireBrut = $fichePaie->getSalaireBrut() ?? '0.00';
        $this->totalPrimes = $fichePaie->getTotalPrimes() ?? '0.00';
        $this->totalDeductions = $fichePaie->getTotalDeductions() ?? '0.00';
        $this->salaireNet = $fichePaie->getSalaireNet() ?? '0.00';
        $this->statutPaiement = $fichePaie->isStatutPaiement();
        $this->notes = $fichePaie->getNotes();
        $this->createdAt = $fichePaie->getCreatedAt();
        $this->updatedAt = $fichePaie->getUpdatedAt();
    }
}
