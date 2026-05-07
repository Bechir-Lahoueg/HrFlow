<?php

namespace App\Entity\Recrutement;

use App\Repository\Recrutement\CandidateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: CandidateRepository::class)]
#[ORM\Table(name: 'candidates')]
#[ORM\Index(name: 'idx_username', columns: ['username'])]
#[ORM\Index(name: 'idx_email', columns: ['email'])]
#[ORM\HasLifecycleCallbacks]
class Candidate implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_CANDIDATE = 'ROLE_CANDIDATE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Username is required')]
    #[Assert\Length(min: 3, max: 100, minMessage: 'Username must be at least 3 characters', maxMessage: 'Username cannot exceed 100 characters')]
    private ?string $username = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Please enter a valid email')]
    #[Assert\Length(max: 255, maxMessage: 'Email cannot exceed 255 characters')]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    #[Assert\Length(min: 6, minMessage: 'Password must be at least 6 characters')]
    private ?string $password = null;

    #[ORM\Column(name: 'first_name', length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'First name cannot exceed 100 characters')]
    private ?string $firstName = null;

    #[ORM\Column(name: 'last_name', length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Last name cannot exceed 100 characters')]
    private ?string $lastName = null;

    #[ORM\Column(name: 'phone', length: 20, nullable: true)]
    #[Assert\Length(max: 20, maxMessage: 'Phone cannot exceed 20 characters')]
    private ?string $phone = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->username ?? '';
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getFullName(): ?string
    {
        if ($this->firstName && $this->lastName) {
            return $this->firstName . ' ' . $this->lastName;
        }
        return $this->firstName ?: $this->lastName ?: $this->username;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
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

    public function getRoles(): array
    {
        return [self::ROLE_CANDIDATE];
    }

    public function eraseCredentials(): void
    {
        // No sensitive data to erase
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
