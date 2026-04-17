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
        $this->id = $fichePaie->getId();
        $this->employeeId = $fichePaie->getEmployee()->getId();
        $this->employeeName = $fichePaie->getEmployee()->getFirstName() . ' ' . $fichePaie->getEmployee()->getLastName();
        $this->mois = $fichePaie->getMois();
        $this->annee = $fichePaie->getAnnee();
        $this->salaireBrut = $fichePaie->getSalaireBrut();
        $this->totalPrimes = $fichePaie->getTotalPrimes();
        $this->totalDeductions = $fichePaie->getTotalDeductions();
        $this->salaireNet = $fichePaie->getSalaireNet();
        $this->statutPaiement = $fichePaie->isStatutPaiement();
        $this->notes = $fichePaie->getNotes();
        $this->createdAt = $fichePaie->getCreatedAt();
        $this->updatedAt = $fichePaie->getUpdatedAt();
    }
}
