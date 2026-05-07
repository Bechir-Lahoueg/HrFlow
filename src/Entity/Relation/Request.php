<?php

namespace App\Entity\Relation;

use App\Repository\Relation\RequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RequestRepository::class)]
#[ORM\Table(name: 'requests')]
#[ORM\Index(name: 'idx_requests_status', columns: ['status'])]
#[ORM\Index(name: 'idx_requests_priority', columns: ['priority'])]
#[ORM\Index(name: 'idx_requests_submitted_date', columns: ['submitted_date'])]
#[ORM\HasLifecycleCallbacks]
class Request
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'user_id', type: Types::INTEGER)]
    private ?int $userId = null;

    #[ORM\Column(name: 'request_type_id', type: Types::INTEGER)]
    private ?int $requestTypeId = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'attachment_url', length: 500, nullable: true)]
    private ?string $attachmentUrl = null;

    #[ORM\Column(length: 30)]
    private ?string $status = null;

    #[ORM\Column(length: 20)]
    private ?string $priority = null;

    #[ORM\Column(name: 'submitted_date', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $submittedDate = null;

    #[ORM\Column(name: 'reviewed_by', type: Types::INTEGER, nullable: true)]
    private ?int $reviewedBy = null;

    #[ORM\Column(name: 'reviewed_date', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $reviewedDate = null;

    #[ORM\Column(name: 'review_comment', type: Types::TEXT, nullable: true)]
    private ?string $reviewComment = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;
        return $this;
    }

    public function getRequestTypeId(): ?int
    {
        return $this->requestTypeId;
    }

    public function setRequestTypeId(int $requestTypeId): static
    {
        $this->requestTypeId = $requestTypeId;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getAttachmentUrl(): ?string
    {
        return $this->attachmentUrl;
    }

    public function setAttachmentUrl(?string $attachmentUrl): static
    {
        $this->attachmentUrl = $attachmentUrl;
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

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function getSubmittedDate(): ?\DateTimeInterface
    {
        return $this->submittedDate;
    }

    public function setSubmittedDate(?\DateTimeInterface $submittedDate): static
    {
        $this->submittedDate = $submittedDate;
        return $this;
    }

    public function getReviewedBy(): ?int
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?int $reviewedBy): static
    {
        $this->reviewedBy = $reviewedBy;
        return $this;
    }

    public function getReviewedDate(): ?\DateTimeInterface
    {
        return $this->reviewedDate;
    }

    public function setReviewedDate(?\DateTimeInterface $reviewedDate): static
    {
        $this->reviewedDate = $reviewedDate;
        return $this;
    }

    public function getReviewComment(): ?string
    {
        return $this->reviewComment;
    }

    public function setReviewComment(?string $reviewComment): static
    {
        $this->reviewComment = $reviewComment;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTime();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }
}

