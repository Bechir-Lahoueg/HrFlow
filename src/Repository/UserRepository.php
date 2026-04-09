<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;

class UserRepository
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Find interviewers (users with RH or ADMIN role)
     *
     * @return array<int, array{id: int, username: string, email: string|null}>
     */
    public function findInterviewers(): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT id, username, email FROM users WHERE role IN ('RH', 'ADMIN') ORDER BY username"
        );
    }

    /**
     * Check if a user exists by ID
     */
    public function exists(int $id): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM users WHERE id = :id',
            ['id' => $id]
        );

        return (bool) $count;
    }

    /**
     * Find a single user by ID
     *
     * @return array{id: int, username: string, email: string|null, role: string}|null
     */
    public function find(int $id): ?array
    {
        $result = $this->connection->fetchAssociative(
            'SELECT id, username, email, role FROM users WHERE id = :id',
            ['id' => $id]
        );

        return $result ?: null;
    }
}
