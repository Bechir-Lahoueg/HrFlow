<?php

namespace App\DTO\Payroll;

use App\Entity\Paie\Deduction;
use App\Enum\DeductionType;

/**
 * Deduction Response DTO - Data transfer object for returning deduction data
 */
class DeductionResponseDTO
{
    public int $id;
    public int $employeeId;
    public string $employeeName;
    public DeductionType $typeDeduction;
    public string $montant;
    public \DateTimeInterface $dateDeduction;
    public ?string $motif;
    public ?\DateTimeInterface $createdAt;
    public ?\DateTimeInterface $updatedAt;

    public function __construct(Deduction $deduction)
    {
        $this->id = $deduction->getId();
        $this->employeeId = $deduction->getEmployee()->getId();
        $this->employeeName = $deduction->getEmployee()->getFirstName() . ' ' . $deduction->getEmployee()->getLastName();
        $this->typeDeduction = $deduction->getTypeDeduction();
        $this->montant = $deduction->getMontant();
        $this->dateDeduction = $deduction->getDateDeduction();
        $this->motif = $deduction->getMotif();
        $this->createdAt = $deduction->getCreatedAt();
        $this->updatedAt = $deduction->getUpdatedAt();
    }
}
