<?php

namespace App\Entity\Formation;

use App\Repository\Formation\SessionFormationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SessionFormationRepository::class)]
#[ORM\Table(name: 'session_formation')]
#[ORM\HasLifecycleCallbacks]
class SessionFormation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_session')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Formation::class, inversedBy: 'sessions')]
    #[ORM\JoinColumn(name: 'id_formation', referencedColumnName: 'id_formation', nullable: false)]
    #[Assert\NotNull(message: "La formation est obligatoire.")]
    private ?Formation $formation = null;

    #[ORM\Column(name: 'date_debut', type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: "La date de début est obligatoire.")]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(name: 'date_fin', type: Types::DATE_MUTABLE)]
    #[Assert\GreaterThanOrEqual(
        propertyPath: 'dateDebut',
        message: "La date de fin doit être supérieure ou égale à la date de début."
    )]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le lieu de la session est obligatoire.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Le lieu ne doit pas dépasser {{ limit }} caractères."
    )]
    private ?string $lieu = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: "Veuillez choisir un mode.")]
    #[Assert\Length(
        max: 50,
        maxMessage: "Le mode ne doit pas dépasser {{ limit }} caractères."
    )]
    private ?string $mode = null;

    #[ORM\Column(name: 'capacite_max')]
    #[Assert\NotNull(message: "La capacité maximale est obligatoire.")]
    #[Assert\Positive(message: "La capacité maximale doit être un nombre positif.")]
    private ?int $capaciteMax = null;

    #[ORM\Column(length: 30)]
    #[Assert\Length(
        max: 30,
        maxMessage: "Le statut ne doit pas dépasser {{ limit }} caractères."
    )]
    private ?string $statut = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    /** @var Collection<int, ParticipationFormation> */
    #[ORM\OneToMany(targetEntity: ParticipationFormation::class, mappedBy: 'session')]
    private Collection $participations;

    public function __construct()
    {
        $this->participations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): static
    {
        $this->formation = $formation;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(?string $lieu): static
    {
        $this->lieu = $lieu;
        return $this;
    }

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function setMode(?string $mode): static
    {
        $this->mode = $mode;
        return $this;
    }

    public function getCapaciteMax(): ?int
    {
        return $this->capaciteMax;
    }

    public function setCapaciteMax(int $capaciteMax): static
    {
        $this->capaciteMax = $capaciteMax;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
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

    /** @return Collection<int, ParticipationFormation> */
    public function getParticipations(): Collection
    {
        return $this->participations;
    }

    public function getAcceptedCount(): int
    {
        return $this->participations->filter(
            fn(ParticipationFormation $p) => $p->getStatutParticipation() === 'Accepte'
        )->count();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
