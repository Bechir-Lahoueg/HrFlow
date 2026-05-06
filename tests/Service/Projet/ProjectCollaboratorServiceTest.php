<?php

namespace App\Tests\Service\Projet;

use App\Repository\Projet\ProjectCollaboratorRepository;
use App\Repository\Projet\ProjectRepository;
use App\Repository\Projet\ProjectTaskRepository;
use App\Service\Projet\ProjectCollaboratorService;
use App\Service\Projet\ProjectService;
use PHPUnit\Framework\TestCase;

class ProjectCollaboratorServiceTest extends TestCase
{
    private ProjectCollaboratorRepository $collaboratorRepository;
    private ProjectRepository $projectRepository;
    private ProjectTaskRepository $taskRepository;

    protected function setUp(): void
    {
        $this->collaboratorRepository = $this->createStub(ProjectCollaboratorRepository::class);
        $this->projectRepository = $this->createStub(ProjectRepository::class);
        $this->taskRepository = $this->createStub(ProjectTaskRepository::class);
    }

    private function createServiceWithProject(array $project): ProjectCollaboratorService
    {
        $this->projectRepository->method('fetchById')->willReturn($project);

        $projectService = new ProjectService(
            $this->projectRepository,
            $this->taskRepository,
            $this->collaboratorRepository
        );

        return new ProjectCollaboratorService($this->collaboratorRepository, $projectService);
    }

    public function testAddCollaboratorEchoueSiHeuresAlloueesDepassentRestant(): void
    {
        $this->collaboratorRepository->method('collaboratorExists')->willReturn(false);
        $this->collaboratorRepository->method('sumAssignedHoursByProject')->willReturn(8);

        $service = $this->createServiceWithProject([
            'id' => 1,
            'estimated_hours' => 10,
        ]);

        $result = $service->addCollaborator([
            'project_id' => 1,
            'employee_id' => 5,
            'assigned_hours' => 5,
        ]);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('assigned_hours', $result['errors']);
    }
}

