<?php

namespace App\Tests\Service\Projet;

use App\Repository\Projet\ProjectCollaboratorRepository;
use App\Repository\Projet\ProjectRepository;
use App\Repository\Projet\ProjectTaskRepository;
use App\Service\Projet\ProjectService;
use App\Service\Projet\ProjectTaskService;
use PHPUnit\Framework\TestCase;

class ProjectTaskServiceTest extends TestCase
{
    private ProjectTaskRepository $taskRepository;
    private ProjectRepository $projectRepository;
    private ProjectCollaboratorRepository $collaboratorRepository;

    protected function setUp(): void
    {
        $this->taskRepository = $this->createStub(ProjectTaskRepository::class);
        $this->projectRepository = $this->createStub(ProjectRepository::class);
        $this->collaboratorRepository = $this->createStub(ProjectCollaboratorRepository::class);
    }

    private function createServiceWithProject(array $project): ProjectTaskService
    {
        $this->projectRepository->method('fetchById')->willReturn($project);

        $projectService = new ProjectService(
            $this->projectRepository,
            $this->taskRepository,
            $this->collaboratorRepository
        );

        return new ProjectTaskService($this->taskRepository, $projectService);
    }

    public function testValidationEchoueSiDateDebutManquante(): void
    {
        $service = $this->createServiceWithProject([
            'id' => 1,
            'end_date' => '2026-12-31',
            'estimated_hours' => 100,
        ]);

        $errors = $service->validate([
            'project_id' => 1,
            'title' => 'Tache A',
            'start_date' => '',
            'estimated_hours' => 10,
        ]);

        $this->assertArrayHasKey('start_date', $errors);
    }

    public function testValidationEchoueSiDateDebutApresFinProjet(): void
    {
        $service = $this->createServiceWithProject([
            'id' => 1,
            'end_date' => '2026-06-01',
            'estimated_hours' => 50,
        ]);

        $errors = $service->validate([
            'project_id' => 1,
            'title' => 'Tache B',
            'start_date' => '2026-06-10',
            'estimated_hours' => 10,
        ]);

        $this->assertArrayHasKey('start_date', $errors);
    }

    public function testValidationEchoueSiHeuresEstimeesDepassentProjet(): void
    {
        $service = $this->createServiceWithProject([
            'id' => 1,
            'end_date' => '2026-12-31',
            'estimated_hours' => 8,
        ]);

        $errors = $service->validate([
            'project_id' => 1,
            'title' => 'Tache C',
            'start_date' => '2026-05-10',
            'estimated_hours' => 12,
        ]);

        $this->assertArrayHasKey('estimated_hours', $errors);
    }
}
