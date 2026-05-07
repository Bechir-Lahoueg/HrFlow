<?php

namespace App\Tests\Service\Projet;

use App\Repository\Projet\ProjectMilestoneRepository;
use App\Service\Projet\ProjectMilestoneService;
use PHPUnit\Framework\TestCase;

class ProjectMilestoneServiceTest extends TestCase
{
    public function testUpdateMilestoneMarqueCompleteQuandCompletionAtteint(): void
    {
        $repository = $this->createMock(ProjectMilestoneRepository::class);
        $repository->expects($this->once())->method('updateMilestone');
        $repository->method('fetchById')->willReturn([
            'id' => 1,
            'status' => 'pending',
        ]);
        $repository->expects($this->once())->method('markCompleted');

        $service = new ProjectMilestoneService($repository);

        $service->updateMilestone(1, [
            'name' => 'Jalon A',
            'target_date' => '2026-06-01',
            'completion_rate' => 100,
        ]);
    }
}

