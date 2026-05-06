<?php

namespace App\Entity\Projet;

use App\Repository\Projet\ProjectMilestoneRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectMilestoneRepository::class)]
#[ORM\Table(name: 'project_milestones')]
#[ORM\Index(name: 'idx_project_milestones_project_id', columns: ['project_id'])]
#[ORM\Index(name: 'idx_project_milestones_target_date', columns: ['target_date'])]
#[ORM\Index(name: 'idx_project_milestones_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class ProjectMilestone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'project_id', type: Types::INTEGER)]
    private ?int $projectId = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'target_date', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $targetDate = null;

    #[ORM\Column(name: 'completion_date', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completionDate = null;

    #[ORM\Column(length: 30)]
    private ?string $status = null;

    #[ORM\Column(name: 'completion_rate', type: Types::INTEGER)]
    private int $completionRate = 0;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getProjectId(): ?int
    {
        return $this->projectId;
    }

    public function setProjectId(int $projectId): static
    {
        $this->projectId = $projectId;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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

    public function getTargetDate(): ?\DateTimeInterface
    {
        return $this->targetDate;
    }

    public function setTargetDate(\DateTimeInterface $targetDate): static
    {
        $this->targetDate = $targetDate;
        return $this;
    }

    public function getCompletionDate(): ?\DateTimeInterface
    {
        return $this->completionDate;
    }

    public function setCompletionDate(?\DateTimeInterface $completionDate): static
    {
        $this->completionDate = $completionDate;
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

    public function getCompletionRate(): int
    {
        return $this->completionRate;
    }

    public function setCompletionRate(int $completionRate): static
    {
        $this->completionRate = $completionRate;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }
}

