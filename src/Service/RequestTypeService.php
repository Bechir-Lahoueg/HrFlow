<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class RequestTypeService
{
    private const MAX_NAME_LENGTH = 255;
    private const MAX_DESCRIPTION_LENGTH = 1000;

    public function __construct(private readonly Connection $connection) {}

    public function getAll(): array
    {
        try {
            return $this->connection->fetchAllAssociative(
                'SELECT * FROM request_types ORDER BY name'
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function getById(int $id): ?array
    {
        try {
            return $this->connection->fetchAssociative(
                'SELECT * FROM request_types WHERE id = ?', [$id]
            ) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function add(array $data): bool
    {
        try {
            $data = $this->normalizeRequestTypeInput($data);
            $this->connection->insert('request_types', [
                'name'              => $data['name'],
                'description'       => $data['description'],
                'requires_approval' => $data['requires_approval'] ? 1 : 0,
                'created_at'        => date('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function update(int $id, array $data): bool
    {
        try {
            $data = $this->normalizeRequestTypeInput($data);
            $this->connection->update('request_types', [
                'name'              => $data['name'],
                'description'       => $data['description'],
                'requires_approval' => $data['requires_approval'] ? 1 : 0,
            ], ['id' => $id]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function delete(int $id): bool
    {
        try {
            $this->connection->delete('request_types', ['id' => $id]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function validate(array $data): array
    {
        $data = $this->normalizeRequestTypeInput($data);
        $errors = [];

        if ($data['name'] === '' || mb_strlen($data['name']) < 3) {
            $errors['name'] = 'Le nom du type doit contenir au moins 3 caractères.';
        } elseif (mb_strlen($data['name']) > self::MAX_NAME_LENGTH) {
            $errors['name'] = 'Le nom du type ne doit pas dépasser ' . self::MAX_NAME_LENGTH . ' caractères.';
        }

        if ($data['description'] !== null && mb_strlen($data['description']) > self::MAX_DESCRIPTION_LENGTH) {
            $errors['description'] = 'La description est trop longue.';
        }

        return $errors;
    }

    private function normalizeRequestTypeInput(array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        $description = $data['description'] ?? null;
        if (is_string($description)) {
            $description = trim($description);
            if ($description === '') {
                $description = null;
            }
        } else {
            $description = null;
        }

        return [
            'name' => $name,
            'description' => $description,
            'requires_approval' => !empty($data['requires_approval']),
        ];
    }
}
