<?php

namespace App\Entity\Formation;

use App\Entity\Rh\Employee;
use App\Repository\Formation\SessionFeedbackRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SessionFeedbackRepository::class)]
#[ORM\Table(name: 'feedback_formation')]
#[ORM\HasLifecycleCallbacks]
class SessionFeedback
{
    /** @phpstan-ignore-next-line */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Employee::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    private ?Employee $employee = null;

    #[ORM\ManyToOne(targetEntity: Formation::class)]
    #[ORM\JoinColumn(name: 'formation_id', referencedColumnName: 'id_formation', nullable: false)]
    private ?Formation $formation = null;

    #[ORM\ManyToOne(targetEntity: SessionFormation::class)]
    #[ORM\JoinColumn(name: 'session_id', referencedColumnName: 'id_session', nullable: false)]
    private ?SessionFormation $session = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $rating = 5;

    #[ORM\Column(name: 'contenu_comment', type: Types::TEXT)]
    private string $contenuComment = '';

    #[ORM\Column(name: 'formateur_comment', type: Types::TEXT, nullable: true)]
    private ?string $formateurComment = null;

    #[ORM\Column(name: 'organisation_comment', type: Types::TEXT, nullable: true)]
    private ?string $organisationComment = null;

    #[ORM\Column(name: 'recommande', type: Types::BOOLEAN)]
    private bool $recommande = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmployee(): ?Employee
    {
        return $this->employee;
    }

    public function setEmployee(Employee $employee): static
    {
        $this->employee = $employee;

        return $this;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(Formation $formation): static
    {
        $this->formation = $formation;

        return $this;
    }

    public function getSession(): ?SessionFormation
    {
        return $this->session;
    }

    public function setSession(SessionFormation $session): static
    {
        $this->session = $session;

        return $this;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = max(1, min(5, $rating));

        return $this;
    }

    public function getComment(): string
    {
        return $this->contenuComment;
    }

    public function setComment(string $comment): static
    {
        $this->contenuComment = $comment;

        return $this;
    }

    public function isAnonymous(): bool
    {
        return $this->organisationComment === 'ANONYMOUS';
    }

    public function setIsAnonymous(bool $isAnonymous): static
    {
        $this->organisationComment = $isAnonymous ? 'ANONYMOUS' : null;

        return $this;
    }

    public function isRecommande(): bool
    {
        return $this->recommande;
    }

    public function setRecommande(bool $recommande): static
    {
        $this->recommande = $recommande;

        return $this;
    }

    public function getDisplayAuthor(): string
    {
        if ($this->isAnonymous()) {
            return 'Participant anonyme';
        }

        return $this->employee?->getFullName() ?: 'Employe';
    }

    public function getFormateurComment(): ?string
    {
        return $this->formateurComment;
    }

    public function setFormateurComment(?string $formateurComment): static
    {
        $this->formateurComment = $formateurComment;

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

