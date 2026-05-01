<?php

namespace App\Entity\Formation;

use App\Repository\Formation\PresenceFormationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PresenceFormationRepository::class)]
#[ORM\Table(name: 'presence_formation')]
class PresenceFormation
{
    /** @phpstan-ignore-next-line */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_presence')]
    private ?int $id = null; // @phpstan-ignore-line

    #[ORM\ManyToOne(targetEntity: ParticipationFormation::class, inversedBy: 'presences')]
    #[ORM\JoinColumn(name: 'id_participation', referencedColumnName: 'id_participation', nullable: false)]
    private ?ParticipationFormation $participation = null;

    #[ORM\Column(name: 'date_presence', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $datePresence = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParticipation(): ?ParticipationFormation
    {
        return $this->participation;
    }

    public function setParticipation(?ParticipationFormation $participation): static
    {
        $this->participation = $participation;
        return $this;
    }

    public function getDatePresence(): ?\DateTimeInterface
    {
        return $this->datePresence;
    }

    public function setDatePresence(\DateTimeInterface $datePresence): static
    {
        $this->datePresence = $datePresence;
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
}
