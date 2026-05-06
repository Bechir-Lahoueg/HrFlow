<?php

namespace App\Entity\Paie;

use App\Enum\PrimeType;
use App\Entity\Rh\Employee;
use App\Repository\Paie\PrimeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PrimeRepository::class)]
#[ORM\Table(name: 'primes')]
#[ORM\Index(name: 'idx_prime_employee', columns: ['employee_id'])]
#[ORM\Index(name: 'idx_prime_date', columns: ['date_attribution'])]
#[ORM\HasLifecycleCallbacks]
class Prime
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Employee::class)]
    #[ORM\JoinColumn(name: 'employee_id', nullable: false)]
    #[Assert\NotNull(message: 'Employee is required')]
    private ?Employee $employee = null;

    #[ORM\Column(name: 'type_prime', type: 'string', enumType: PrimeType::class)]
    #[Assert\NotNull(message: 'Prime type is required')]
    private PrimeType $typePrime;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\NotNull(message: 'Amount is required')]
    #[Assert\GreaterThan(value: 0, message: 'Amount must be positive')]
    private ?string $montant = '0.00';

    #[ORM\Column(name: 'date_attribution', type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'Attribution date is required')]
    private ?\DateTimeInterface $dateAttribution = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

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

    public function getTypePrime(): PrimeType
    {
        return $this->typePrime;
    }

    public function setTypePrime(PrimeType $typePrime): static
    {
        $this->typePrime = $typePrime;
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

    public function getDateAttribution(): ?\DateTimeInterface
    {
        return $this->dateAttribution;
    }

    public function setDateAttribution(\DateTimeInterface $dateAttribution): static
    {
        $this->dateAttribution = $dateAttribution;
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

    public function setCreatedAt(\DateTimeInterface $createdAt): static
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
        return (int) ($this->dateAttribution?->format('m') ?? 0);
    }

    public function getYear(): int
    {
        return (int) ($this->dateAttribution?->format('Y') ?? 0);
    }
}

