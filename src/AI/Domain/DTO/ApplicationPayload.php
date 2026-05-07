<?php

declare(strict_types=1);

namespace App\AI\Domain\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ApplicationPayload
{
    public function __construct(
        #[Assert\Choice(['list', 'view', 'move', 'rank', 'create', 'delete'])]
        public string $action,

        #[Assert\Positive]
        public ?int $id = null,

        public ?int $job_offer_id = null,
        public ?string $candidate_name = null,
        public ?string $email = null,

        #[Assert\Choice(['PENDING', 'REVIEWING', 'INTERVIEW', 'OFFER', 'HIRED', 'REJECTED'])]
        public ?string $new_status = null,

        #[Assert\Choice(['PENDING', 'REVIEWING', 'INTERVIEW', 'OFFER', 'HIRED', 'REJECTED'])]
        public ?string $status = null,

        #[Assert\Positive]
        #[Assert\LessThan(200)]
        public ?int $limit = 50,
    ) {}
}
