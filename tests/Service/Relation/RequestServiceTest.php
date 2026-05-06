<?php

namespace App\Tests\Service\Relation;

use App\Repository\Relation\RequestRepository;
use App\Service\RequestService;
use PHPUnit\Framework\TestCase;

class RequestServiceTest extends TestCase
{
    public function testValidationEchoueSiPrioriteInvalide(): void
    {
        $service = new RequestService($this->createStub(RequestRepository::class));

        $errors = $service->validateCreate([
            'user_id' => 1,
            'request_type_id' => 2,
            'title' => 'Demande A',
            'priority' => 'urgent',
            'description' => 'Contenu',
        ]);

        $this->assertArrayHasKey('priority', $errors);
    }

    public function testValidationEchoueSiTitreTropCourt(): void
    {
        $service = new RequestService($this->createStub(RequestRepository::class));

        $errors = $service->validateCreate([
            'user_id' => 1,
            'request_type_id' => 2,
            'title' => 'Hi',
            'priority' => 'low',
        ]);

        $this->assertArrayHasKey('title', $errors);
    }
}

