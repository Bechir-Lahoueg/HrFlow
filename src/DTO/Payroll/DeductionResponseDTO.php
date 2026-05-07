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
        $this->id = $deduction->getId() ?? 0;
        $employee = $deduction->getEmployee();
        $this->employeeId = $employee?->getId() ?? 0;
        $this->employeeName = ($employee?->getFirstName() ?? '') . ' ' . ($employee?->getLastName() ?? '');
        $this->typeDeduction = $deduction->getTypeDeduction();
        $this->montant = $deduction->getMontant() ?? '0.00';
        $this->dateDeduction = $deduction->getDateDeduction() ?? new \DateTimeImmutable();
        $this->motif = $deduction->getMotif();
        $this->createdAt = $deduction->getCreatedAt();
        $this->updatedAt = $deduction->getUpdatedAt();
    }
}
