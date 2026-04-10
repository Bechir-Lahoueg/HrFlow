<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class DbUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(
        private readonly int $id,
        private readonly string $username,
        private readonly ?string $email,
        private readonly string $password,
        private readonly string $role,
        private readonly string $source = 'users',
        private readonly ?string $firstName = null,
        private readonly ?string $lastName = null,
        private readonly ?string $jobTitle = null,
        private readonly ?int $age = null,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function getFullName(): ?string
    {
        if ($this->firstName && $this->lastName) {
            return $this->firstName . ' ' . $this->lastName;
        }

        return null;
    }

    public function getJobTitle(): ?string
    {
        return $this->jobTitle;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function isEmployee(): bool
    {
        return $this->source === 'employees';
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        $normalizedRole = strtoupper($this->role);

        $mappedRole = match ($normalizedRole) {
            'ADMIN' => 'ROLE_ADMIN',
            'RH' => 'ROLE_RH',
            'EMPLOYEE' => 'ROLE_EMPLOYEE',
            'CANDIDATE' => 'ROLE_CANDIDATE',
            default => str_starts_with($normalizedRole, 'ROLE_') ? $normalizedRole : 'ROLE_USER',
        };

        return array_values(array_unique([$mappedRole]));
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function eraseCredentials(): void
    {
    }
}
