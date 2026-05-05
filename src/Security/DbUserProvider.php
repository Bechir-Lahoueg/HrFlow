<?php

namespace App\Security;

use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @implements UserProviderInterface<DbUser> */
final class DbUserProvider implements UserProviderInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // First try the users table (Admin / RH)
        $row = $this->connection->fetchAssociative(
            'SELECT id, username, email, password, role, department FROM users WHERE username = :identifier OR email = :identifier LIMIT 1',
            ['identifier' => $identifier]
        );

        if ($row) {
            return $this->hydrateUser($row);
        }

        // Then try the employees table (like the Java app)
        $empRow = $this->connection->fetchAssociative(
            'SELECT id, first_name, last_name, age, job_title, email, password, rh_id, department FROM employees WHERE email = :identifier LIMIT 1',
            ['identifier' => $identifier]
        );

        if ($empRow) {
            return $this->hydrateEmployee($empRow);
        }

        // Finally try the candidates table (external applicants)
        $candidateRow = $this->connection->fetchAssociative(
            'SELECT id, username, email, password, first_name, last_name, phone FROM candidates WHERE username = :identifier OR email = :identifier LIMIT 1',
            ['identifier' => $identifier]
        );

        if ($candidateRow) {
            return $this->hydrateCandidate($candidateRow);
        }

        throw new UserNotFoundException(sprintf('User "%s" was not found.', $identifier));
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof DbUser) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        if ($user->getSource() === 'employees') {
            $row = $this->connection->fetchAssociative(
                'SELECT id, first_name, last_name, age, job_title, email, password, rh_id, department FROM employees WHERE id = :id LIMIT 1',
                ['id' => $user->getId()]
            );

            if (!$row) {
                throw new UserNotFoundException(sprintf('Employee id "%d" was not found.', $user->getId()));
            }

            return $this->hydrateEmployee($row);
        }

        if ($user->getSource() === 'candidates') {
            $row = $this->connection->fetchAssociative(
                'SELECT id, username, email, password, first_name, last_name, phone FROM candidates WHERE id = :id LIMIT 1',
                ['id' => $user->getId()]
            );

            if (!$row) {
                throw new UserNotFoundException(sprintf('Candidate id "%d" was not found.', $user->getId()));
            }

            return $this->hydrateCandidate($row);
        }

        $row = $this->connection->fetchAssociative(
            'SELECT id, username, email, password, role, department FROM users WHERE id = :id LIMIT 1',
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

    /** @param array<string, mixed> $row */
    private function hydrateUser(array $row): DbUser
    {
        return new DbUser(
            (int) $row['id'],
            (string) $row['username'],
            isset($row['email']) ? (string) $row['email'] : null,
            (string) $row['password'],
            (string) $row['role'],
            'users',
            null,
            null,
            null,
            null,
            null,
            isset($row['department']) ? (string) $row['department'] : null,
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrateEmployee(array $row): DbUser
    {
        $firstName = (string) $row['first_name'];
        $lastName = (string) $row['last_name'];

        return new DbUser(
            (int) $row['id'],
            $firstName . ' ' . $lastName,
            (string) $row['email'],
            (string) $row['password'],
            'EMPLOYEE',
            'employees',
            $firstName,
            $lastName,
            (string) $row['job_title'],
            (int) $row['age'],
            $row['rh_id'] ? (int) $row['rh_id'] : null,
            isset($row['department']) ? (string) $row['department'] : null,
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrateCandidate(array $row): DbUser
    {
        return new DbUser(
            (int) $row['id'],
            (string) $row['username'],
            (string) $row['email'],
            (string) $row['password'],
            'CANDIDATE',
            'candidates',
            isset($row['first_name']) ? (string) $row['first_name'] : null,
            isset($row['last_name']) ? (string) $row['last_name'] : null,
            null,
            null,
            null
        );
    }
}
