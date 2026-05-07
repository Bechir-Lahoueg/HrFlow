<?php

namespace App\Entity\Formation;

use App\Entity\Rh\Employee;
use App\Repository\Formation\ParticipationFormationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParticipationFormationRepository::class)]
#[ORM\Table(name: 'participation_formation')]
class ParticipationFormation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_participation')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Employee::class)]
    #[ORM\JoinColumn(name: 'id_utilisateur_id', nullable: false)]
    private ?Employee $employee = null;

    #[ORM\ManyToOne(targetEntity: SessionFormation::class, inversedBy: 'participations')]
    #[ORM\JoinColumn(name: 'id_session_id', referencedColumnName: 'id_session', nullable: false, onDelete: 'CASCADE')]
    private ?SessionFormation $session = null;

    #[ORM\Column(name: 'date_inscription', type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $dateInscription;

    #[ORM\Column(name: 'statut_participation', length: 30)]
    private ?string $statutParticipation = null;

    #[ORM\Column(name: 'certificat_obtenu', type: Types::BOOLEAN)]
    private bool $certificatObtenu = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    /** @var Collection<int, PresenceFormation> */
    #[ORM\OneToMany(targetEntity: PresenceFormation::class, mappedBy: 'participation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $presences;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $token = null;

    #[ORM\Column(name: 'quiz_score', type: Types::INTEGER, nullable: true)]
    private ?int $quizScore = null;

    #[ORM\Column(name: 'quiz_passed', type: Types::BOOLEAN)]
    private bool $quizPassed = false;

    #[ORM\Column(name: 'quiz_attempted_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $quizAttemptedAt = null;

    #[ORM\Column(name: 'quiz_correct_count', type: Types::INTEGER, nullable: true)]
    private ?int $quizCorrectCount = null;

    #[ORM\Column(name: 'quiz_total_questions', type: Types::INTEGER, nullable: true)]
    private ?int $quizTotalQuestions = null;

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): static
    {
        $this->token = $token;
        return $this;
    }

    public function __construct()
    {
        $this->presences = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmployee(): ?Employee
    {
        return $this->employee;
    }

    public function setEmployee(?Employee $employee): static
    {
        $this->employee = $employee;
        return $this;
    }

    public function getSession(): ?SessionFormation
    {
        return $this->session;
    }

    public function setSession(?SessionFormation $session): static
    {
        $this->session = $session;
        return $this;
    }

    public function getDateInscription(): \DateTimeInterface
    {
        return $this->dateInscription;
    }

    public function setDateInscription(\DateTimeInterface $dateInscription): static
    {
        $this->dateInscription = $dateInscription;
        return $this;
    }

    public function getStatutParticipation(): ?string
    {
        return $this->statutParticipation;
    }

    public function setStatutParticipation(string $statutParticipation): static
    {
        $this->statutParticipation = $statutParticipation;
        return $this;
    }

    public function isCertificatObtenu(): bool
    {
        return $this->certificatObtenu;
    }

    public function setCertificatObtenu(bool $certificatObtenu): static
    {
        $this->certificatObtenu = $certificatObtenu;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /** @return Collection<int, PresenceFormation> */
    public function getPresences(): Collection
    {
        return $this->presences;
    }

    public function getQuizScore(): ?int
    {
        return $this->quizScore;
    }

    public function setQuizScore(?int $quizScore): static
    {
        $this->quizScore = $quizScore;
        return $this;
    }

    public function isQuizPassed(): bool
    {
        return $this->quizPassed;
    }

    public function setQuizPassed(bool $quizPassed): static
    {
        $this->quizPassed = $quizPassed;
        return $this;
    }

    public function getQuizAttemptedAt(): ?\DateTimeInterface
    {
        return $this->quizAttemptedAt;
    }

    public function setQuizAttemptedAt(?\DateTimeInterface $quizAttemptedAt): static
    {
        $this->quizAttemptedAt = $quizAttemptedAt;
        return $this;
    }

    public function getQuizCorrectCount(): ?int
    {
        return $this->quizCorrectCount;
    }

    public function setQuizCorrectCount(?int $quizCorrectCount): static
    {
        $this->quizCorrectCount = $quizCorrectCount;
        return $this;
    }

    public function getQuizTotalQuestions(): ?int
    {
        return $this->quizTotalQuestions;
    }

    public function setQuizTotalQuestions(?int $quizTotalQuestions): static
    {
        $this->quizTotalQuestions = $quizTotalQuestions;
        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }
}

