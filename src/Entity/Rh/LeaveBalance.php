<?php

namespace App\Entity\Rh;

use App\Repository\Rh\LeaveBalanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LeaveBalanceRepository::class)]
#[ORM\Table(name: 'leave_balance')]
class LeaveBalance
{
    /** @phpstan-ignore-next-line */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Employee::class)]
    #[ORM\JoinColumn(name: 'employee_id', nullable: false)]
    private ?Employee $employee = null;

    #[ORM\Column(name: 'employee_name', length: 200, nullable: true)]
    private ?string $employeeName = null;

    #[ORM\Column(name: 'available_days', type: Types::DECIMAL, precision: 8, scale: 2)]
    private string $availableDays = '0.00';

    #[ORM\Column(name: 'total_accrued', type: Types::DECIMAL, precision: 8, scale: 2)]
    private string $totalAccrued = '0.00';

    #[ORM\Column(name: 'total_used', type: Types::DECIMAL, precision: 8, scale: 2)]
    private string $totalUsed = '0.00';

    #[ORM\Column(name: 'last_accrual_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastAccrualDate = null;

    #[ORM\Column(name: 'hire_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeInterface $hireDate = null;

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

    public function getEmployeeName(): ?string
    {
        return $this->employeeName;
    }

    public function setEmployeeName(?string $employeeName): static
    {
        $this->employeeName = $employeeName;
        return $this;
    }

    public function getAvailableDays(): float
    {
        return (float) $this->availableDays;
    }

    public function setAvailableDays(float $availableDays): static
    {
        $this->availableDays = (string) round($availableDays, 2);
        return $this;
    }

    public function getTotalAccrued(): float
    {
        return (float) $this->totalAccrued;
    }

    public function setTotalAccrued(float $totalAccrued): static
    {
        $this->totalAccrued = (string) round($totalAccrued, 2);
        return $this;
    }

    public function getTotalUsed(): float
    {
        return (float) $this->totalUsed;
    }

    public function setTotalUsed(float $totalUsed): static
    {
        $this->totalUsed = (string) round($totalUsed, 2);
        return $this;
    }

    public function getLastAccrualDate(): ?\DateTimeInterface
    {
        return $this->lastAccrualDate;
    }

    public function setLastAccrualDate(?\DateTimeInterface $lastAccrualDate): static
    {
        $this->lastAccrualDate = $lastAccrualDate;
        return $this;
    }

    public function getHireDate(): ?\DateTimeInterface
    {
        return $this->hireDate;
    }

    public function setHireDate(?\DateTimeInterface $hireDate): static
    {
        $this->hireDate = $hireDate;
        return $this;
    }
}
