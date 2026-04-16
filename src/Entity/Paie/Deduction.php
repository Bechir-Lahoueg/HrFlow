<?php

namespace App\Entity\Paie;

use App\Enum\DeductionType;
use App\Entity\Rh\Employee;
use App\Repository\Paie\DeductionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DeductionRepository::class)]
#[ORM\Table(name: 'deductions')]
#[ORM\Index(name: 'idx_deduction_employee', columns: ['employee_id'])]
#[ORM\Index(name: 'idx_deduction_date', columns: ['date_deduction'])]
#[ORM\HasLifecycleCallbacks]
class Deduction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Employee::class)]
    #[ORM\JoinColumn(name: 'employee_id', nullable: false)]
    #[Assert\NotNull(message: 'Employee is required')]
    private ?Employee $employee = null;

    #[ORM\Column(name: 'type_deduction', type: 'string', enumType: DeductionType::class)]
    #[Assert\NotNull(message: 'Deduction type is required')]
    private DeductionType $typeDeduction;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\NotNull(message: 'Amount is required')]
    #[Assert\GreaterThan(value: 0, message: 'Amount must be positive')]
    private ?string $montant = '0.00';

    #[ORM\Column(name: 'date_deduction', type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'Deduction date is required')]
    private ?\DateTimeInterface $dateDeduction = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmployee(): ?Employee
    {
        return $this->employee;
    }

    public function setEmployee(?Employee $employee): static
    {
        $this->employee = $employee;
        return $this;
    }

    public function getTypeDeduction(): DeductionType
    {
        return $this->typeDeduction;
    }

    public function setTypeDeduction(DeductionType $typeDeduction): static
    {
        $this->typeDeduction = $typeDeduction;
        return $this;
    }

    public function getMontant(): ?string
    {
        return $this->montant;
    }

    public function setMontant(string $montant): static
    {
        $this->montant = $montant;
        return $this;
    }

    public function getDateDeduction(): ?\DateTimeInterface
    {
        return $this->dateDeduction;
    }

    public function setDateDeduction(\DateTimeInterface $dateDeduction): static
    {
        $this->dateDeduction = $dateDeduction;
        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): static
    {
        $this->motif = $motif;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getMonth(): int
    {
        return (int) $this->dateDeduction->format('m');
    }

    public function getYear(): int
    {
        return (int) $this->dateDeduction->format('Y');
    }
}
