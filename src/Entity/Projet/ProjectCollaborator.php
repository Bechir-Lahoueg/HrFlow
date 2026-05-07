<?php

namespace App\Entity\Projet;

use App\Repository\Projet\ProjectCollaboratorRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectCollaboratorRepository::class)]
#[ORM\Table(name: 'project_collaborators')]
#[ORM\Index(name: 'idx_project_collaborators_project_id', columns: ['project_id'])]
#[ORM\Index(name: 'idx_project_collaborators_employee_id', columns: ['employee_id'])]
#[ORM\Index(name: 'idx_project_collaborators_active', columns: ['is_active'])]
#[ORM\HasLifecycleCallbacks]
class ProjectCollaborator
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore-next-line */
    private ?int $id = null;

    #[ORM\Column(name: 'project_id', type: Types::INTEGER)]
    private ?int $projectId = null;

    #[ORM\Column(name: 'employee_id', type: Types::INTEGER)]
    private ?int $employeeId = null;

    #[ORM\Column(length: 120)]
    private ?string $role = null;

    #[ORM\Column(name: 'assigned_hours', type: Types::INTEGER, nullable: true)]
    private ?int $assignedHours = null;

    #[ORM\Column(name: 'worked_hours', type: Types::INTEGER)]
    private int $workedHours = 0;

    #[ORM\Column(name: 'joined_date', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $joinedDate = null;

    #[ORM\Column(name: 'left_date', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $leftDate = null;

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

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

    public function getEmployeeId(): ?int
    {
        return $this->employeeId;
    }

    public function setEmployeeId(int $employeeId): static
    {
        $this->employeeId = $employeeId;
        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getAssignedHours(): ?int
    {
        return $this->assignedHours;
    }

    public function setAssignedHours(?int $assignedHours): static
    {
        $this->assignedHours = $assignedHours;
        return $this;
    }

    public function getWorkedHours(): int
    {
        return $this->workedHours;
    }

    public function setWorkedHours(int $workedHours): static
    {
        $this->workedHours = $workedHours;
        return $this;
    }

    public function getJoinedDate(): ?\DateTimeInterface
    {
        return $this->joinedDate;
    }

    public function setJoinedDate(\DateTimeInterface $joinedDate): static
    {
        $this->joinedDate = $joinedDate;
        return $this;
    }

    public function getLeftDate(): ?\DateTimeInterface
    {
        return $this->leftDate;
    }

    public function setLeftDate(?\DateTimeInterface $leftDate): static
    {
        $this->leftDate = $leftDate;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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

