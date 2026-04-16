<?php

namespace App\DTO\Payroll;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * FichePaie Request DTO - Data transfer object for creating/updating pay slips
 */
class FichePaieRequestDTO
{
    #[Assert\NotNull(message: 'Employee ID is required')]
    #[Assert\GreaterThan(value: 0)]
    public ?int $employeeId = null;

    #[Assert\NotNull(message: 'Month is required')]
    #[Assert\Range(min: 1, max: 12, notInRangeMessage: 'Month must be between 1 and 12')]
    public ?int $mois = null;

    #[Assert\NotNull(message: 'Year is required')]
    #[Assert\Range(min: 2000, max: 2100, notInRangeMessage: 'Year must be valid')]
    public ?int $annee = null;

    #[Assert\NotNull(message: 'Gross salary is required')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Gross salary must be positive')]
    public ?string $salaireBrut = null;

    #[Assert\Length(max: 1000, maxMessage: 'Notes cannot exceed 1000 characters')]
    public ?string $notes = null;

    public function __construct(
        ?int $employeeId = null,
        ?int $mois = null,
        ?int $annee = null,
        ?string $salaireBrut = null,
        ?string $notes = null,
    ) {
        $this->employeeId = $employeeId;
        $this->mois = $mois;
        $this->annee = $annee;
        $this->salaireBrut = $salaireBrut;
        $this->notes = $notes;
    }
}
