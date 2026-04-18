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
    #[ORM\JoinColumn(name: 'id_utilisateur', nullable: false)]
    private ?Employee $employee = null;

    #[ORM\ManyToOne(targetEntity: SessionFormation::class, inversedBy: 'participations')]
    #[ORM\JoinColumn(name: 'id_session', referencedColumnName: 'id_session', nullable: false)]
    private ?SessionFormation $session = null;

    #[ORM\Column(name: 'date_inscription', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateInscription = null;

    #[ORM\Column(name: 'statut_participation', length: 30)]
    private ?string $statutParticipation = null;

    #[ORM\Column(name: 'certificat_obtenu', type: Types::BOOLEAN)]
    private bool $certificatObtenu = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    /** @var Collection<int, PresenceFormation> */
    #[ORM\OneToMany(targetEntity: PresenceFormation::class, mappedBy: 'participation')]
    private Collection $presences;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $token = null;

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

    public function getDateInscription(): ?\DateTimeInterface
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

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }
}
