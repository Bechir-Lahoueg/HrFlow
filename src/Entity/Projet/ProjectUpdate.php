<?php

namespace App\Entity\Projet;

use App\Repository\Projet\ProjectUpdateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectUpdateRepository::class)]
#[ORM\Table(name: 'project_updates')]
#[ORM\Index(name: 'idx_project_updates_project_id', columns: ['project_id'])]
#[ORM\Index(name: 'idx_project_updates_created_at', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class ProjectUpdate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'project_id', type: Types::INTEGER)]
    private ?int $projectId = null;

    #[ORM\Column(name: 'user_id', type: Types::INTEGER)]
    private ?int $userId = null;

    #[ORM\Column(name: 'update_type', length: 40)]
    private ?string $updateType = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

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

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;
        return $this;
    }

    public function getUpdateType(): ?string
    {
        return $this->updateType;
    }

    public function setUpdateType(string $updateType): static
    {
        $this->updateType = $updateType;
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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;
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

