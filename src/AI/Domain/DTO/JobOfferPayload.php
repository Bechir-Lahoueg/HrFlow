<?php

declare(strict_types=1);

namespace App\AI\Domain\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class JobOfferPayload
{
    public function __construct(
        #[Assert\Choice(['list', 'search', 'view', 'create', 'update', 'change_status', 'delete'])]
        public string $action,

        #[Assert\Positive]
        public ?int $id = null,

        #[Assert\Choice(['DRAFT', 'OPEN', 'CLOSED', 'ON_HOLD'])]
        public ?string $status = null,

        public ?string $department = null,
        public ?string $search = null,

        #[Assert\Positive]
        #[Assert\LessThan(200)]
        public ?int $limit = 50,

        public ?string $title = null,
        public ?string $description = null,
        public ?string $location = null,
        public ?string $employment_type = null,

        #[Assert\PositiveOrZero]
        public ?float $salary_min = null,

        #[Assert\PositiveOrZero]
        public ?float $salary_max = null,

        #[Assert\Choice(['DRAFT', 'OPEN', 'CLOSED', 'ON_HOLD'])]
        public ?string $new_status = null,
    ) {}
}
