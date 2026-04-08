<?php

namespace App\Entity;

use App\Repository\FichePaieRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FichePaieRepository::class)]
#[ORM\Table(name: 'fiches_paie')]
#[ORM\Index(name: 'idx_fp_employee', columns: ['employee_id'])]
#[ORM\Index(name: 'idx_fp_period', columns: ['mois', 'annee'])]
#[ORM\UniqueConstraint(name: 'unique_fiche_paie', columns: ['employee_id', 'mois', 'annee'])]
#[ORM\HasLifecycleCallbacks]
class FichePaie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Employee::class)]
    #[ORM\JoinColumn(name: 'employee_id', nullable: false)]
    #[Assert\NotNull(message: 'Employee is required')]
    private ?Employee $employee = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotNull(message: 'Month is required')]
    #[Assert\Range(min: 1, max: 12, notInRangeMessage: 'Month must be between 1 and 12')]
    private ?int $mois = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotNull(message: 'Year is required')]
    #[Assert\Range(min: 2000, max: 2100, notInRangeMessage: 'Year must be valid')]
    private ?int $annee = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\NotNull(message: 'Gross salary is required')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Gross salary must be positive')]
    private ?string $salaireBrut = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Total bonuses must be positive')]
    private ?string $totalPrimes = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Total deductions must be positive')]
    private ?string $totalDeductions = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private ?string $salaireNet = '0.00';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->calculateSalaireNet();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
        $this->calculateSalaireNet();
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

    public function getMois(): ?int
    {
        return $this->mois;
    }

    public function setMois(int $mois): static
    {
        $this->mois = $mois;
        return $this;
    }

    public function getAnnee(): ?int
    {
        return $this->annee;
    }

    public function setAnnee(int $annee): static
    {
        $this->annee = $annee;
        return $this;
    }

    public function getSalaireBrut(): ?string
    {
        return $this->salaireBrut;
    }

    public function setSalaireBrut(string $salaireBrut): static
    {
        $this->salaireBrut = $salaireBrut;
        return $this;
    }

    public function getTotalPrimes(): ?string
    {
        return $this->totalPrimes;
    }

    public function setTotalPrimes(string $totalPrimes): static
    {
        $this->totalPrimes = $totalPrimes;
        return $this;
    }

    public function getTotalDeductions(): ?string
    {
        return $this->totalDeductions;
    }

    public function setTotalDeductions(string $totalDeductions): static
    {
        $this->totalDeductions = $totalDeductions;
        return $this;
    }

    public function getSalaireNet(): ?string
    {
        return $this->salaireNet;
    }

    public function setSalaireNet(string $salaireNet): static
    {
        $this->salaireNet = $salaireNet;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
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

    public function calculateSalaireNet(): void
    {
        $brut = (float) $this->salaireBrut;
        $primes = (float) $this->totalPrimes;
        $deductions = (float) $this->totalDeductions;
        $net = $brut + $primes - $deductions;
        $this->salaireNet = (string) number_format($net, 2, '.', '');
    }

    public function getPeriodLabel(): string
    {
        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];
        return ($months[$this->mois] ?? 'Invalid') . ' ' . $this->annee;
    }
}
