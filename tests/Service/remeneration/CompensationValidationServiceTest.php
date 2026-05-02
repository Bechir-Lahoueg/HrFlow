<?php

namespace App\Tests\Service\Remeneration;

use App\Exception\Payroll\InvalidCompensationAmountException;
use App\Service\Paie\CompensationValidationService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CompensationValidationServiceTest extends TestCase
{
    private CompensationValidationService $service;

    protected function setUp(): void
    {
        $this->service = new CompensationValidationService();
    }

    // ─────────── validatePrimeAmount ───────────

    public function testValidatePrimeAmountNeLancePasExceptionSiMontantValide(): void
    {
        // Bonus range: 100-300, so 150 is valid
        $this->service->validatePrimeAmount('Bonus', 150.0);
        $this->assertTrue(true);
    }

    public function testValidatePrimeAmountLanceExceptionSiMontantTropBas(): void
    {
        $this->expectException(InvalidCompensationAmountException::class);
        $this->service->validatePrimeAmount('Bonus', 50.0); // min is 100
    }

    public function testValidatePrimeAmountLanceExceptionSiMontantTropEleve(): void
    {
        $this->expectException(InvalidCompensationAmountException::class);
        $this->service->validatePrimeAmount('Bonus', 500.0); // max is 300
    }

    public function testValidatePrimeAmountNeFaitRienSiTypeInconnu(): void
    {
        // Unknown type → validation skipped
        $this->service->validatePrimeAmount('Type Inexistant', 9999.0);
        $this->assertTrue(true);
    }

    public function testValidatePrimeAmountAutrePrimeAccepteNImporteQuelMontant(): void
    {
        // 'Autre Prime' has range 0-10000 → no real restriction
        $this->service->validatePrimeAmount('Autre Prime', 5000.0);
        $this->assertTrue(true);
    }

    public function testValidatePrimeAmountLanceExceptionAvecMessageContenant(): void
    {
        try {
            $this->service->validatePrimeAmount('Prime', 50.0); // min is 150
            $this->fail('Exception attendue non lancée');
        } catch (InvalidCompensationAmountException $e) {
            $this->assertStringContainsString('Prime', $e->getMessage());
            $this->assertStringContainsString('150', $e->getMessage());
        }
    }

    // ─────────── validateDeductionAmount ───────────

    public function testValidateDeductionAmountNeLancePasExceptionSiMontantValide(): void
    {
        // 'Assurance Mutuelle' range: 20-80
        $this->service->validateDeductionAmount('Assurance Mutuelle', 50.0);
        $this->assertTrue(true);
    }

    public function testValidateDeductionAmountLanceExceptionSiHorsPlage(): void
    {
        $this->expectException(InvalidCompensationAmountException::class);
        $this->service->validateDeductionAmount('Assurance Mutuelle', 150.0); // max is 80
    }

    public function testValidateDeductionAmountIgnoreDeductionBaseeePourcentage(): void
    {
        // IRPP has max=0 → validation skipped (percentage-based)
        $this->service->validateDeductionAmount('Impôt sur le Revenu (IRPP)', 9999.0);
        $this->assertTrue(true);
    }

    public function testValidateDeductionAmountIgnoreAvanceSurSalaire(): void
    {
        // 'Avance sur Salaire' has max=0 → variable, no limit
        $this->service->validateDeductionAmount('Avance sur Salaire', 10000.0);
        $this->assertTrue(true);
    }

    public function testValidateDeductionAmountNeFaitRienSiTypeInconnu(): void
    {
        $this->service->validateDeductionAmount('Type Inexistant', 9999.0);
        $this->assertTrue(true);
    }

    // ─────────── getPrimeRange / getDeductionRange ───────────

    public function testGetPrimeRangeRetourneTableauPourTypeConnu(): void
    {
        $range = $this->service->getPrimeRange('Bonus');
        $this->assertIsArray($range);
        $this->assertSame(100, $range['min']);
        $this->assertSame(300, $range['max']);
    }

    public function testGetPrimeRangeRetourneNullPourTypeInconnu(): void
    {
        $this->assertNull($this->service->getPrimeRange('Type Inexistant'));
    }

    public function testGetDeductionRangeRetourneTableauPourTypeConnu(): void
    {
        $range = $this->service->getDeductionRange('Assurance Mutuelle');
        $this->assertIsArray($range);
        $this->assertSame(20, $range['min']);
        $this->assertSame(80, $range['max']);
    }

    public function testGetDeductionRangeRetourneNullPourTypeInconnu(): void
    {
        $this->assertNull($this->service->getDeductionRange('Type Inexistant'));
    }

    public function testGetAllPrimeRangesRetourneTableauComplet(): void
    {
        $ranges = $this->service->getAllPrimeRanges();
        $this->assertIsArray($ranges);
        $this->assertArrayHasKey('Bonus', $ranges);
        $this->assertArrayHasKey('Autre Prime', $ranges);
        $this->assertArrayHasKey('Prime Exceptionnelle', $ranges);
    }

    public function testGetAllDeductionRangesRetourneTableauComplet(): void
    {
        $ranges = $this->service->getAllDeductionRanges();
        $this->assertIsArray($ranges);
        $this->assertArrayHasKey('Assurance Mutuelle', $ranges);
        $this->assertArrayHasKey('Cotisation CNSS', $ranges);
    }
}
