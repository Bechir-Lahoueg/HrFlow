<?php

namespace App\Entity\Rh;

use App\Repository\Rh\LeaveRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LeaveRequestRepository::class)]
#[ORM\Table(name: 'leave_requests')]
#[ORM\Index(name: 'idx_lr_employee', columns: ['employee_id'])]
#[ORM\Index(name: 'idx_lr_status', columns: ['status'])]
class LeaveRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Employee::class)]
    #[ORM\JoinColumn(name: 'employee_id', nullable: false)]
    private ?Employee $employee = null;

    #[ORM\Column(name: 'employee_name', length: 200)]
    private ?string $employeeName = null;

    #[ORM\Column(name: 'start_date', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(name: 'end_date', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(name: 'leave_type', length: 50)]
    private ?string $leaveType = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column(name: 'request_date', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $requestDate = null;

    #[ORM\Column(name: 'rh_comment', type: Types::TEXT, nullable: true)]
    private ?string $rhComment = null;

    #[ORM\Column(name: 'days_count')]
    private ?int $daysCount = null;

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

    public function setEmployeeName(string $employeeName): static
    {
        $this->employeeName = $employeeName;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getLeaveType(): ?string
    {
        return $this->leaveType;
    }

    public function setLeaveType(string $leaveType): static
    {
        $this->leaveType = $leaveType;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getRequestDate(): ?\DateTimeInterface
    {
        return $this->requestDate;
    }

    public function setRequestDate(\DateTimeInterface $requestDate): static
    {
        $this->requestDate = $requestDate;
        return $this;
    }

    public function getRhComment(): ?string
    {
        return $this->rhComment;
    }

    public function setRhComment(?string $rhComment): static
    {
        $this->rhComment = $rhComment;
        return $this;
    }

    public function getDaysCount(): ?int
    {
        return $this->daysCount;
    }

    public function setDaysCount(int $daysCount): static
    {
        $this->daysCount = $daysCount;
        return $this;
    }
}
