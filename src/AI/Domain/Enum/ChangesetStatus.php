<?php

declare(strict_types=1);

namespace App\AI\Domain\Enum;

enum ChangesetStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case REVERTED = 'reverted';
    case EXPIRED = 'expired';
}