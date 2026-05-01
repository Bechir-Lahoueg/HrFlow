<?php

namespace App\Entity\Projet;

use App\Repository\Projet\ProjectTaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectTaskRepository::class)]
#[ORM\Table(name: 'project_tasks')]
#[ORM\Index(name: 'idx_project_tasks_project_id', columns: ['project_id'])]
#[ORM\Index(name: 'idx_project_tasks_assigned_to', columns: ['assigned_to'])]
#[ORM\Index(name: 'idx_project_tasks_status', columns: ['status'])]
#[ORM\Index(name: 'idx_project_tasks_due_date', columns: ['due_date'])]
#[ORM\HasLifecycleCallbacks]
class ProjectTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore-next-line */
    private ?int $id = null;

    #[ORM\Column(name: 'project_id', type: Types::INTEGER)]
    private ?int $projectId = null;

    #[ORM\Column(name: 'assigned_to', type: Types::INTEGER, nullable: true)]
    private ?int $assignedTo = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 30)]
    private ?string $status = null;

    #[ORM\Column(length: 20)]
    private ?string $priority = null;

    #[ORM\Column(name: 'estimated_hours', type: Types::INTEGER, nullable: true)]
    private ?int $estimatedHours = null;

    #[ORM\Column(name: 'actual_hours', type: Types::INTEGER)]
    private int $actualHours = 0;

    #[ORM\Column(name: 'due_date', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column(name: 'completed_date', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedDate = null;

    #[ORM\Column(name: 'order_index', type: Types::INTEGER)]
    private int $orderIndex = 0;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAssignedTo(): ?int
    {
        return $this->assignedTo;
    }

    public function setAssignedTo(?int $assignedTo): static
    {
        $this->assignedTo = $assignedTo;
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

    public function getEstimatedHours(): ?int
    {
        return $this->estimatedHours;
    }

    public function setEstimatedHours(?int $estimatedHours): static
    {
        $this->estimatedHours = $estimatedHours;
        return $this;
    }

    public function getActualHours(): int
    {
        return $this->actualHours;
    }

    public function setActualHours(int $actualHours): static
    {
        $this->actualHours = $actualHours;
        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeInterface $dueDate): static
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getCompletedDate(): ?\DateTimeInterface
    {
        return $this->completedDate;
    }

    public function setCompletedDate(?\DateTimeInterface $completedDate): static
    {
        $this->completedDate = $completedDate;
        return $this;
    }

    public function getOrderIndex(): int
    {
        return $this->orderIndex;
    }

    public function setOrderIndex(int $orderIndex): static
    {
        $this->orderIndex = $orderIndex;
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

