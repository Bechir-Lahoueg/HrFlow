<?php

namespace App\Service;

use App\Repository\Relation\RequestTypeRepository;

final class RequestTypeService
{
    private const MAX_NAME_LENGTH = 255;
    private const MAX_DESCRIPTION_LENGTH = 1000;

    public function __construct(private readonly RequestTypeRepository $requestTypeRepository) {}

    /** @return array<int, array<string, mixed>> */
    public function getAll(): array
    {
        try {
            return $this->requestTypeRepository->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed>|null */
    public function getById(int $id): ?array
    {
        try {
            return $this->requestTypeRepository->fetchById($id);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $data */
    public function add(array $data): bool
    {
        try {
            $data = $this->normalizeRequestTypeInput($data);
            $this->requestTypeRepository->insert([
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

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        try {
            $data = $this->normalizeRequestTypeInput($data);
            $this->requestTypeRepository->updateRequestType($id, [
                'name'              => $data['name'],
                'description'       => $data['description'],
                'requires_approval' => $data['requires_approval'] ? 1 : 0,
            ]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function delete(int $id): bool
    {
        try {
            $this->requestTypeRepository->deleteRequestType($id);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
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

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
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
