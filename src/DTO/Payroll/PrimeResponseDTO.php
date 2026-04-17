<?php

namespace App\DTO\Payroll;

use App\Entity\Paie\Prime;
use App\Enum\PrimeType;

/**
 * Prime Response DTO - Data transfer object for returning bonus data
 */
class PrimeResponseDTO
{
    public int $id;
    public int $employeeId;
    public string $employeeName;
    public PrimeType $typePrime;
    public string $montant;
    public \DateTimeInterface $dateAttribution;
    public ?string $motif;
    public ?\DateTimeInterface $createdAt;
    public ?\DateTimeInterface $updatedAt;

    public function __construct(Prime $prime)
    {
        $this->id = $prime->getId();
        $this->employeeId = $prime->getEmployee()->getId();
        $this->employeeName = $prime->getEmployee()->getFirstName() . ' ' . $prime->getEmployee()->getLastName();
        $this->typePrime = $prime->getTypePrime();
        $this->montant = $prime->getMontant();
        $this->dateAttribution = $prime->getDateAttribution();
        $this->motif = $prime->getMotif();
        $this->createdAt = $prime->getCreatedAt();
        $this->updatedAt = $prime->getUpdatedAt();
    }
}
