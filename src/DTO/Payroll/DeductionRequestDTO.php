<?php

namespace App\DTO\Payroll;

use App\Enum\DeductionType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Deduction Request DTO - Data transfer object for creating/updating deductions
 */
class DeductionRequestDTO
{
    #[Assert\NotNull(message: 'Employee ID is required')]
    #[Assert\GreaterThan(value: 0)]
    public ?int $employeeId = null;

    #[Assert\NotNull(message: 'Deduction type is required')]
    public ?DeductionType $typeDeduction = null;

    #[Assert\NotNull(message: 'Amount is required')]
    #[Assert\GreaterThan(value: 0, message: 'Amount must be positive')]
    public ?string $montant = null;

    #[Assert\NotNull(message: 'Deduction date is required')]
    #[Assert\Type(\DateTimeInterface::class, message: 'Deduction date must be a valid date')]
    public ?\DateTimeInterface $dateDeduction = null;

    #[Assert\Length(max: 1000, maxMessage: 'Reason cannot exceed 1000 characters')]
    public ?string $motif = null;

    public function __construct(
        ?int $employeeId = null,
        ?DeductionType $typeDeduction = null,
        ?string $montant = null,
        ?\DateTimeInterface $dateDeduction = null,
        ?string $motif = null,
    ) {
        $this->employeeId = $employeeId;
        $this->typeDeduction = $typeDeduction;
        $this->montant = $montant;
        $this->dateDeduction = $dateDeduction;
        $this->motif = $motif;
    }
}
