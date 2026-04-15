<?php

namespace App\Entity\Rh;

use App\Repository\Rh\LeaveNotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LeaveNotificationRepository::class)]
#[ORM\Table(name: 'leave_notifications')]
#[ORM\Index(name: 'idx_leave_notif_recipient', columns: ['recipient_type', 'recipient_id', 'is_read'])]
#[ORM\HasLifecycleCallbacks]
class LeaveNotification
{
    public const RECIPIENT_EMPLOYEE = 'EMPLOYEE';
    public const RECIPIENT_USER = 'USER';

    public const TYPE_LEAVE_SUBMITTED = 'leave_submitted';
    public const TYPE_LEAVE_APPROVED = 'leave_approved';
    public const TYPE_LEAVE_REJECTED = 'leave_rejected';
    public const TYPE_EXCEPTION_PENDING_ADMIN = 'exception_pending_admin';
    public const TYPE_EXCEPTION_APPROVED = 'exception_approved';
    public const TYPE_EXCEPTION_REJECTED = 'exception_rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'recipient_type', length: 20)]
    private string $recipientType;

    #[ORM\Column(name: 'recipient_id')]
    private int $recipientId;

    #[ORM\Column(name: 'leave_request_id', nullable: true)]
    private ?int $leaveRequestId = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $message = '';

    #[ORM\Column(length: 40)]
    private string $type = self::TYPE_LEAVE_SUBMITTED;

    #[ORM\Column(name: 'is_read', type: Types::BOOLEAN)]
    private bool $isRead = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipientType(): string
    {
        return $this->recipientType;
    }

    public function setRecipientType(string $recipientType): static
    {
        $this->recipientType = $recipientType;
        return $this;
    }

    public function getRecipientId(): int
    {
        return $this->recipientId;
    }

    public function setRecipientId(int $recipientId): static
    {
        $this->recipientId = $recipientId;
        return $this;
    }

    public function getLeaveRequestId(): ?int
    {
        return $this->leaveRequestId;
    }

    public function setLeaveRequestId(?int $leaveRequestId): static
    {
        $this->leaveRequestId = $leaveRequestId;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
    }
}
