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
