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
        $this->id = $prime->getId() ?? 0;
        $employee = $prime->getEmployee();
        $this->employeeId = $employee?->getId() ?? 0;
        $this->employeeName = ($employee?->getFirstName() ?? '') . ' ' . ($employee?->getLastName() ?? '');
        $this->typePrime = $prime->getTypePrime();
        $this->montant = $prime->getMontant() ?? '0.00';
        $this->dateAttribution = $prime->getDateAttribution() ?? new \DateTimeImmutable();
        $this->motif = $prime->getMotif();
        $this->createdAt = $prime->getCreatedAt();
        $this->updatedAt = $prime->getUpdatedAt();
    }
}
