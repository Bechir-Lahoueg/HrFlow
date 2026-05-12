<?php

declare(strict_types=1);

namespace App\AI\Domain\Enum;

enum IntentType: string
{
    case GREETING = 'GREETING';
    case DATA_QUERY = 'DATA_QUERY';
    case PIPELINE_ANALYSIS = 'PIPELINE_ANALYSIS';
    case CANDIDATE_ANALYSIS = 'CANDIDATE_ANALYSIS';
    case MUTATION = 'MUTATION';
    case SCHEDULING = 'SCHEDULING';
    case REPORT_GENERATION = 'REPORT_GENERATION';
    case UNKNOWN = 'UNKNOWN';
}