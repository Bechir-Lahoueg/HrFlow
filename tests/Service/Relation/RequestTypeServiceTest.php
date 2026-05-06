<?php

namespace App\Tests\Service\Relation;

use App\Repository\Relation\RequestTypeRepository;
use App\Service\RequestTypeService;
use PHPUnit\Framework\TestCase;

class RequestTypeServiceTest extends TestCase
{
    public function testValidationEchoueSiNomTropLong(): void
    {
        $service = new RequestTypeService($this->createStub(RequestTypeRepository::class));

        $errors = $service->validate([
            'name' => str_repeat('A', 300),
            'description' => 'Type long',
        ]);

        $this->assertArrayHasKey('name', $errors);
    }
}

