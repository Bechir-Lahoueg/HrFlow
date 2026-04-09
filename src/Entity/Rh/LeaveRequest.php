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

    #[ORM\Column(name: 'request_category', length: 20, options: ['default' => 'NORMAL'])]
    private string $requestCategory = 'NORMAL';

    #[ORM\Column(name: 'workflow_status', length: 50, nullable: true)]
    private ?string $workflowStatus = null;

    #[ORM\Column(name: 'urgency_level', length: 20, nullable: true)]
    private ?string $urgencyLevel = null;

    #[ORM\Column(name: 'expected_return_date', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expectedReturnDate = null;

    #[ORM\Column(name: 'attachment_path', length: 255, nullable: true)]
    private ?string $attachmentPath = null;

    #[ORM\Column(name: 'admin_comment', type: Types::TEXT, nullable: true)]
    private ?string $adminComment = null;

    #[ORM\Column(name: 'rh_decision_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $rhDecisionAt = null;

    #[ORM\Column(name: 'rh_decision_by', length: 120, nullable: true)]
    private ?string $rhDecisionBy = null;

    #[ORM\Column(name: 'admin_decision_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $adminDecisionAt = null;

    #[ORM\Column(name: 'admin_decision_by', length: 120, nullable: true)]
    private ?string $adminDecisionBy = null;

    #[ORM\Column(name: 'audit_log', type: Types::TEXT, nullable: true)]
    private ?string $auditLog = null;

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

    public function getRequestCategory(): string
    {
        return $this->requestCategory;
    }

    public function setRequestCategory(string $requestCategory): static
    {
        $this->requestCategory = $requestCategory;
        return $this;
    }

    public function getWorkflowStatus(): ?string
    {
        return $this->workflowStatus;
    }

    public function setWorkflowStatus(?string $workflowStatus): static
    {
        $this->workflowStatus = $workflowStatus;
        return $this;
    }

    public function getUrgencyLevel(): ?string
    {
        return $this->urgencyLevel;
    }

    public function setUrgencyLevel(?string $urgencyLevel): static
    {
        $this->urgencyLevel = $urgencyLevel;
        return $this;
    }

    public function getExpectedReturnDate(): ?\DateTimeInterface
    {
        return $this->expectedReturnDate;
    }

    public function setExpectedReturnDate(?\DateTimeInterface $expectedReturnDate): static
    {
        $this->expectedReturnDate = $expectedReturnDate;
        return $this;
    }

    public function getAttachmentPath(): ?string
    {
        return $this->attachmentPath;
    }

    public function setAttachmentPath(?string $attachmentPath): static
    {
        $this->attachmentPath = $attachmentPath;
        return $this;
    }

    public function getAdminComment(): ?string
    {
        return $this->adminComment;
    }

    public function setAdminComment(?string $adminComment): static
    {
        $this->adminComment = $adminComment;
        return $this;
    }

    public function getRhDecisionAt(): ?\DateTimeInterface
    {
        return $this->rhDecisionAt;
    }

    public function setRhDecisionAt(?\DateTimeInterface $rhDecisionAt): static
    {
        $this->rhDecisionAt = $rhDecisionAt;
        return $this;
    }

    public function getRhDecisionBy(): ?string
    {
        return $this->rhDecisionBy;
    }

    public function setRhDecisionBy(?string $rhDecisionBy): static
    {
        $this->rhDecisionBy = $rhDecisionBy;
        return $this;
    }

    public function getAdminDecisionAt(): ?\DateTimeInterface
    {
        return $this->adminDecisionAt;
    }

    public function setAdminDecisionAt(?\DateTimeInterface $adminDecisionAt): static
    {
        $this->adminDecisionAt = $adminDecisionAt;
        return $this;
    }

    public function getAdminDecisionBy(): ?string
    {
        return $this->adminDecisionBy;
    }

    public function setAdminDecisionBy(?string $adminDecisionBy): static
    {
        $this->adminDecisionBy = $adminDecisionBy;
        return $this;
    }

    public function getAuditLog(): ?string
    {
        return $this->auditLog;
    }

    public function setAuditLog(?string $auditLog): static
    {
        $this->auditLog = $auditLog;
        return $this;
    }

    public function appendAuditLog(string $actor, string $action, ?string $comment = null): static
    {
        $line = sprintf(
            "[%s] %s | %s%s",
            (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            $actor,
            $action,
            $comment !== null && $comment !== '' ? ' | ' . $comment : ''
        );

        $this->auditLog = trim((string) $this->auditLog) === ''
            ? $line
            : ((string) $this->auditLog) . "\n" . $line;

        return $this;
    }
}
