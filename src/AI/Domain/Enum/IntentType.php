<?php

declare(strict_types=1);

namespace App\AI\Domain\Enum;

enum IntentType: string
{
    case GREETING = 'greeting';
    case DATA_QUERY = 'data_query';
    case MUTATION = 'mutation';
    case SCHEDULE = 'schedule';
    case REPORT = 'report';
}