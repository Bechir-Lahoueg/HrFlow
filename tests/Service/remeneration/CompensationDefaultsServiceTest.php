<?php

namespace App\Tests\Service\Remeneration;

use App\Service\Paie\CompensationDefaultsService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CompensationDefaultsServiceTest extends TestCase
{
    private CompensationDefaultsService $service;

    protected function setUp(): void
    {
        $this->service = new CompensationDefaultsService();
    }

    // ─────────── getPrimeDefault ───────────

    public function testGetPrimeDefaultRetourneValeurCorrecteBonus(): void
    {
        $this->assertSame(100.0, $this->service->getPrimeDefault('Bonus'));
    }

    public function testGetPrimeDefaultRetourneValeurCorrectePrime(): void
    {
        $this->assertSame(150.0, $this->service->getPrimeDefault('Prime'));
    }

    public function testGetPrimeDefaultRetourneValeurCorrecteIndemnite(): void
    {
        $this->assertSame(50.0, $this->service->getPrimeDefault('Indemnité'));
    }

    public function testGetPrimeDefaultRetourneZeroPourTypeInconnu(): void
    {
        $this->assertSame(0.0, $this->service->getPrimeDefault('Type Inexistant'));
    }

    public function testGetPrimeDefaultAutrePrimeRetourneZero(): void
    {
        $this->assertSame(0.0, $this->service->getPrimeDefault('Autre Prime'));
    }

    // ─────────── getDeductionDefault ───────────

    public function testGetDeductionDefaultRetourneValeurCorrecteAssuranceMutuelle(): void
    {
        $this->assertSame(20.0, $this->service->getDeductionDefault('Assurance Mutuelle'));
    }

    public function testGetDeductionDefaultRetourneValeurCorrecteChargeSyndicale(): void
    {
        $this->assertSame(10.0, $this->service->getDeductionDefault('Charge Syndicale'));
    }

    public function testGetDeductionDefaultRetourneZeroPourIrpp(): void
    {
        // IRPP is percentage-based → default 0 (user must calculate)
        $this->assertSame(0.0, $this->service->getDeductionDefault('Impôt sur le Revenu (IRPP)'));
    }

    public function testGetDeductionDefaultRetourneZeroPourTypeInconnu(): void
    {
        $this->assertSame(0.0, $this->service->getDeductionDefault('Type Inexistant'));
    }

    // ─────────── getAllPrimeDefaults ───────────

    public function testGetAllPrimeDefaultsRetourneTableauAvecTousLesTypes(): void
    {
        $defaults = $this->service->getAllPrimeDefaults();
        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('Bonus', $defaults);
        $this->assertArrayHasKey('Autre Prime', $defaults);
        $this->assertArrayHasKey('Gratification', $defaults);
        $this->assertCount(9, $defaults);
    }

    public function testGetAllPrimeDefaultsRetourneDesFloat(): void
    {
        $defaults = $this->service->getAllPrimeDefaults();
        foreach ($defaults as $value) {
            $this->assertIsFloat($value);
        }
    }

    // ─────────── getAllDeductionDefaults ───────────

    public function testGetAllDeductionDefaultsRetourneTableauAvecTousLesTypes(): void
    {
        $defaults = $this->service->getAllDeductionDefaults();
        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('Assurance Mutuelle', $defaults);
        $this->assertArrayHasKey('Cotisation CNSS', $defaults);
        $this->assertCount(10, $defaults);
    }

    public function testGetAllDeductionDefaultsRetourneDesFloat(): void
    {
        $defaults = $this->service->getAllDeductionDefaults();
        foreach ($defaults as $value) {
            $this->assertIsFloat($value);
        }
    }

    // ─────────── getDefaultByType ───────────

    public function testGetDefaultByTypeCategoriePrimeParDefaut(): void
    {
        // Default category is 'prime'
        $this->assertSame(100.0, $this->service->getDefaultByType('Bonus'));
    }

    public function testGetDefaultByTypeCategorieExplicitePrime(): void
    {
        $this->assertSame(150.0, $this->service->getDefaultByType('Prime', 'prime'));
    }

    public function testGetDefaultByTypeCategorieDeduction(): void
    {
        $this->assertSame(20.0, $this->service->getDefaultByType('Assurance Mutuelle', 'deduction'));
    }

    public function testGetDefaultByTypeTypeInconnuRetourneZero(): void
    {
        $this->assertSame(0.0, $this->service->getDefaultByType('Type Inexistant', 'prime'));
        $this->assertSame(0.0, $this->service->getDefaultByType('Type Inexistant', 'deduction'));
    }
}
