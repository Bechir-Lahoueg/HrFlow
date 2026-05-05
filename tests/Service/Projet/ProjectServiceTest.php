<?php

namespace App\Tests\Service\Projet;

use App\Repository\Projet\ProjectCollaboratorRepository;
use App\Repository\Projet\ProjectRepository;
use App\Repository\Projet\ProjectTaskRepository;
use App\Service\Projet\ProjectService;
use PHPUnit\Framework\TestCase;

class ProjectServiceTest extends TestCase
{
    public function testValidationEchoueSiStatutInvalide(): void
    {
        $service = new ProjectService(
            $this->createStub(ProjectRepository::class),
            $this->createStub(ProjectTaskRepository::class),
            $this->createStub(ProjectCollaboratorRepository::class)
        );

        $errors = $service->validate([
            'name' => 'Projet A',
            'status' => 'archived',
            'priority' => 'medium',
            'start_date' => '2026-05-01',
            'end_date' => '2026-06-01',
            'estimated_hours' => 10,
        ]);

        $this->assertArrayHasKey('status', $errors);
    }
}

