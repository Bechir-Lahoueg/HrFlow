<?php

namespace App\DTO\Payroll;

use App\Enum\PrimeType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Prime Request DTO - Data transfer object for creating/updating bonuses
 */
class PrimeRequestDTO
{
    #[Assert\NotNull(message: 'Employee ID is required')]
    #[Assert\GreaterThan(value: 0)]
    public ?int $employeeId = null;

    #[Assert\NotNull(message: 'Prime type is required')]
    public ?PrimeType $typePrime = null;

    #[Assert\NotNull(message: 'Amount is required')]
    #[Assert\GreaterThan(value: 0, message: 'Amount must be positive')]
    public ?string $montant = null;

    #[Assert\NotNull(message: 'Attribution date is required')]
    #[Assert\Type(\DateTimeInterface::class, message: 'Attribution date must be a valid date')]
    public ?\DateTimeInterface $dateAttribution = null;

    #[Assert\Length(max: 1000, maxMessage: 'Reason cannot exceed 1000 characters')]
    public ?string $motif = null;

    public function __construct(
        ?int $employeeId = null,
        ?PrimeType $typePrime = null,
        ?string $montant = null,
        ?\DateTimeInterface $dateAttribution = null,
        ?string $motif = null,
    ) {
        $this->employeeId = $employeeId;
        $this->typePrime = $typePrime;
        $this->montant = $montant;
        $this->dateAttribution = $dateAttribution;
        $this->motif = $motif;
    }
}
