<?php

namespace App\Entity\Relation;

use App\Repository\Relation\FeedbackRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FeedbackRepository::class)]
#[ORM\Table(name: 'feedbacks')]
#[ORM\Index(name: 'idx_feedbacks_to_user', columns: ['to_user_id'])]
#[ORM\Index(name: 'idx_feedbacks_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class Feedback
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'from_user_id', type: Types::INTEGER)]
    private ?int $fromUserId = null;

    #[ORM\Column(name: 'to_user_id', type: Types::INTEGER)]
    private ?int $toUserId = null;

    #[ORM\Column(name: 'feedback_type', length: 40)]
    private ?string $feedbackType = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $rating = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(name: 'is_anonymous', type: Types::BOOLEAN)]
    private bool $isAnonymous = false;

    #[ORM\Column(length: 30)]
    private string $status = 'pending';

    #[ORM\Column(name: 'emotion_label', length: 50, nullable: true)]
    private ?string $emotionLabel = null;

    #[ORM\Column(name: 'emotion_score', type: Types::FLOAT, nullable: true)]
    private ?float $emotionScore = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFromUserId(): ?int
    {
        return $this->fromUserId;
    }

    public function setFromUserId(int $fromUserId): static
    {
        $this->fromUserId = $fromUserId;
        return $this;
    }

    public function getToUserId(): ?int
    {
        return $this->toUserId;
    }

    public function setToUserId(int $toUserId): static
    {
        $this->toUserId = $toUserId;
        return $this;
    }

    public function getFeedbackType(): ?string
    {
        return $this->feedbackType;
    }

    public function setFeedbackType(string $feedbackType): static
    {
        $this->feedbackType = $feedbackType;
        return $this;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    public function isAnonymous(): bool
    {
        return $this->isAnonymous;
    }

    public function setIsAnonymous(bool $isAnonymous): static
    {
        $this->isAnonymous = $isAnonymous;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getEmotionLabel(): ?string
    {
        return $this->emotionLabel;
    }

    public function setEmotionLabel(?string $emotionLabel): static
    {
        $this->emotionLabel = $emotionLabel;
        return $this;
    }

    public function getEmotionScore(): ?float
    {
        return $this->emotionScore;
    }

    public function setEmotionScore(?float $emotionScore): static
    {
        $this->emotionScore = $emotionScore;
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

