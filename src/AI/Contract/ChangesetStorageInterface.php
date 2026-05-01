<?php

declare(strict_types=1);

namespace App\AI\Contract;

use App\AI\Domain\ValueObject\PendingChangeset;

interface ChangesetStorageInterface
{
    public function persist(PendingChangeset $changeset): void;

    public function find(string $id): ?PendingChangeset;

    public function findBySession(string $sessionId): array;
}