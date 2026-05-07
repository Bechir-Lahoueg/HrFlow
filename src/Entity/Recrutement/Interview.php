<?php

namespace App\Entity\Recrutement;

use App\Repository\Recrutement\InterviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InterviewRepository::class)]
#[ORM\Table(name: 'interviews')]
#[ORM\Index(name: 'idx_interviews_date', columns: ['interview_date'])]
#[ORM\Index(name: 'idx_interviews_result', columns: ['result'])]
#[ORM\Index(name: 'idx_interviews_type', columns: ['type'])]
#[ORM\Index(name: 'idx_interviews_is_deleted', columns: ['is_deleted'])]
class Interview
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Application::class, inversedBy: 'interviews')]
    #[ORM\JoinColumn(name: 'application_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull(message: 'Application is required')]
    private ?Application $application = null;

    #[ORM\Column(name: 'interviewer_id', type: Types::INTEGER, nullable: false)]
    #[Assert\NotNull(message: 'Interviewer is required')]
    private ?int $interviewerId = null;

    #[ORM\Column(name: 'interview_date', type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'Interview date is required')]
    #[Assert\GreaterThan('now', message: 'Interview date must be in the future')]
    private ?\DateTimeInterface $interviewDate = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank(message: 'Type is required')]
    #[Assert\Length(max: 30, maxMessage: 'Type cannot exceed 30 characters')]
    private ?string $type = null;

    #[ORM\Column(name: 'meeting_link', length: 500, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'Meeting link cannot exceed 500 characters')]
    #[Assert\Url(message: 'Please enter a valid URL')]
    private ?string $meetingLink = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Location cannot exceed 255 characters')]
    private ?string $location = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $feedback = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Score must be at least 0')]
    #[Assert\LessThanOrEqual(value: 100, message: 'Score cannot exceed 100')]
    private int $score = 0;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Result is required')]
    #[Assert\Length(max: 20, maxMessage: 'Result cannot exceed 20 characters')]
    private ?string $result = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isDeleted = false;

    public function getId(): int
    {
        return $this->id;
    }

    public function getApplication(): ?Application
    {
        return $this->application;
    }

    public function setApplication(?Application $application): static
    {
        $this->application = $application;
        return $this;
    }

    public function getInterviewerId(): ?int
    {
        return $this->interviewerId;
    }

    public function setInterviewerId(?int $interviewerId): static
    {
        $this->interviewerId = $interviewerId;
        return $this;
    }

    public function getInterviewDate(): ?\DateTimeInterface
    {
        return $this->interviewDate;
    }

    public function setInterviewDate(\DateTimeInterface $interviewDate): static
    {
        $this->interviewDate = $interviewDate;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getMeetingLink(): ?string
    {
        return $this->meetingLink;
    }

    public function setMeetingLink(?string $meetingLink): static
    {
        $this->meetingLink = $meetingLink;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function getFeedback(): ?string
    {
        return $this->feedback;
    }

    public function setFeedback(?string $feedback): static
    {
        $this->feedback = $feedback;
        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;
        return $this;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setResult(string $result): static
    {
        $this->result = $result;
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): static
    {
        $this->isDeleted = $isDeleted;
        return $this;
    }
}
