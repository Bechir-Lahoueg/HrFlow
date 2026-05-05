<?php

namespace App\Tests\Service\Relation;

use App\Repository\Relation\FeedbackRepository;
use App\Service\FeedbackService;
use PHPUnit\Framework\TestCase;

class FeedbackServiceTest extends TestCase
{
    public function testValidationEchoueSiAutoEvaluation(): void
    {
        $service = new FeedbackService($this->createStub(FeedbackRepository::class));

        $errors = $service->validateCreate([
            'from_user_id' => 3,
            'to_user_id' => 3,
            'feedback_type' => 'performance',
            'rating' => 4,
            'comment' => 'Test',
        ]);

        $this->assertArrayHasKey('to_user_id', $errors);
    }
}

