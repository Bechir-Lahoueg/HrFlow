<?php

namespace App\Security;

use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class DbUserProvider implements UserProviderInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, username, email, password, role FROM users WHERE username = :identifier OR email = :identifier LIMIT 1',
            ['identifier' => $identifier]
        );

        if (!$row) {
            throw new UserNotFoundException(sprintf('User "%s" was not found.', $identifier));
        }

        return $this->hydrateUser($row);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof DbUser) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $row = $this->connection->fetchAssociative(
            'SELECT id, username, email, password, role FROM users WHERE id = :id LIMIT 1',
            ['id' => $user->getId()]
        );

        if (!$row) {
            throw new UserNotFoundException(sprintf('User id "%d" was not found.', $user->getId()));
        }

        return $this->hydrateUser($row);
    }

    public function supportsClass(string $class): bool
    {
        return DbUser::class === $class;
    }

    private function hydrateUser(array $row): DbUser
    {
        return new DbUser(
            (int) $row['id'],
            (string) $row['username'],
            isset($row['email']) ? (string) $row['email'] : null,
            (string) $row['password'],
            (string) $row['role']
        );
    }
}
