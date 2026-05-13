<?php

declare(strict_types=1);

namespace App\AI\Domain\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class InterviewPayload
{
    public function __construct(
        #[Assert\Choice(['list', 'view', 'schedule', 'update', 'cancel', 'delete'])]
        public string $action,

        #[Assert\Positive]
        public ?int $id = null,

        public ?int $application_id = null,
        public ?string $from_date = null,
        public ?string $to_date = null,

        #[Assert\Choice(['PHONE', 'VIDEO', 'IN_PERSON', 'TECHNICAL'])]
        public ?string $type = null,

        public ?string $date = null,

        #[Assert\Positive]
        #[Assert\LessThan(480)]
        public ?int $duration = 60,

        public ?string $notes = null,
        public ?string $meeting_link = null,
        public ?string $location = null,

        #[Assert\Choice(['SCHEDULED', 'COMPLETED', 'CANCELLED'])]
        public ?string $result = null,

        #[Assert\Positive]
        #[Assert\LessThan(200)]
        public ?int $limit = 50,
    ) {}
}
