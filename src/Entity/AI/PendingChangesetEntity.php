<?php

declare(strict_types=1);

namespace App\Entity\AI;

use App\Repository\AI\PendingChangesetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PendingChangesetRepository::class)]
#[ORM\Table(name: 'pending_changesets')]
#[ORM\Index(name: 'idx_changeset_session', columns: ['session_id'])]
#[ORM\Index(name: 'idx_changeset_status', columns: ['status'])]
#[ORM\Index(name: 'idx_changeset_created', columns: ['created_at'])]
class PendingChangesetEntity
{
    #[ORM\Id]
    #[ORM\Column(length: 32)]
    private ?string $id = null;

    #[ORM\Column(name: 'session_id', length: 64)]
    private ?string $sessionId = null;

    #[ORM\Column(length: 64)]
    private ?string $tool = null;

    #[ORM\Column(length: 32)]
    private ?string $action = null;

    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'confirmed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): static
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function getTool(): ?string
    {
        return $this->tool;
    }

    public function setTool(string $tool): static
    {
        $this->tool = $tool;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): static
    {
        $this->payload = $payload;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): static
    {
        $this->confirmedAt = $confirmedAt;
        return $this;
    }
}