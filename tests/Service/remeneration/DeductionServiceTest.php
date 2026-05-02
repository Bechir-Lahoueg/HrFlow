<?php

namespace App\Tests\Service\Remeneration;

use App\DTO\Payroll\DeductionRequestDTO;
use App\Entity\Paie\Deduction;
use App\Entity\Rh\Employee;
use App\Enum\DeductionType;
use App\Exception\Payroll\EmployeeNotFoundException;
use App\Exception\Payroll\InvalidCompensationAmountException;
use App\Repository\Paie\DeductionRepository;
use App\Repository\Rh\EmployeeRepository;
use App\Service\Paie\CompensationValidationService;
use App\Service\Paie\DeductionService;
use App\Service\Paie\FichePaieService;
use App\Service\Shared\CachingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class DeductionServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private DeductionRepository&MockObject    $deductionRepository;
    private EmployeeRepository&MockObject     $employeeRepository;
    private CachingService&MockObject         $cachingService;
    private FichePaieService&MockObject       $fichePaieService;
    private DeductionService                  $service;

    protected function setUp(): void
    {
        $this->em                   = $this->createMock(EntityManagerInterface::class);
        $this->deductionRepository  = $this->createMock(DeductionRepository::class);
        $this->employeeRepository   = $this->createMock(EmployeeRepository::class);
        $this->cachingService       = $this->createMock(CachingService::class);
        $this->fichePaieService     = $this->createMock(FichePaieService::class);

        // Use the real CompensationValidationService (no deps, no mocking needed)
        $validationService = new CompensationValidationService();

        $this->service = new DeductionService(
            $this->em,
            $this->deductionRepository,
            $this->employeeRepository,
            $this->cachingService,
            $this->fichePaieService,
            $validationService,
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function createEmployeeMock(int $id = 1): Employee
    {
        $employee = $this->createMock(Employee::class);
        $employee->method('getId')->willReturn($id);
        $employee->method('getFirstName')->willReturn('Bob');
        $employee->method('getLastName')->willReturn('Martin');
        return $employee;
    }

    /**
     * Creates a mock Deduction (as returned from repository).
     */
    private function createDeductionMock(Employee $employee, int $id = 1, string $montant = '50.00'): Deduction
    {
        $date      = new \DateTimeImmutable('2025-05-15');
        $deduction = $this->createMock(Deduction::class);
        $deduction->method('getId')->willReturn($id);
        $deduction->method('getEmployee')->willReturn($employee);
        $deduction->method('getTypeDeduction')->willReturn(DeductionType::MUTUELLE);
        $deduction->method('getMontant')->willReturn($montant);
        $deduction->method('getDateDeduction')->willReturn($date);
        $deduction->method('getMotif')->willReturn(null);
        $deduction->method('getCreatedAt')->willReturn(null);
        $deduction->method('getUpdatedAt')->willReturn(null);
        $deduction->method('getMonth')->willReturn(5);
        $deduction->method('getYear')->willReturn(2025);
        return $deduction;
    }

    /**
     * Creates a real Deduction entity with ID set via reflection.
     */
    private function createDeductionEntity(Employee $employee, int $id = 1): Deduction
    {
        $deduction = new Deduction();
        $deduction->setEmployee($employee)
            ->setTypeDeduction(DeductionType::MUTUELLE)
            ->setMontant('50.00')
            ->setDateDeduction(new \DateTime('2025-05-15'))
            ->setMotif('Test');

        $r = new \ReflectionProperty(Deduction::class, 'id');
        $r->setValue($deduction, $id);

        return $deduction;
    }

    /**
     * Creates a valid DeductionRequestDTO (Assurance Mutuelle: 20-80 range).
     */
    private function createValidDto(int $employeeId = 1, float $montant = 50.0): DeductionRequestDTO
    {
        return new DeductionRequestDTO(
            $employeeId,
            DeductionType::MUTUELLE,
            (string) $montant,
            new \DateTime('2025-05-15'),
            'Cotisation mensuelle',
        );
    }

    // ── getDeductionById ──────────────────────────────────────────────────

    public function testGetDeductionByIdLanceDomainExceptionSiAbsent(): void
    {
        $this->deductionRepository->method('find')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->service->getDeductionById(99);
    }

    public function testGetDeductionByIdRetourneDtoSiTrouve(): void
    {
        $employee  = $this->createEmployeeMock();
        $deduction = $this->createDeductionMock($employee, 1, '40.00');
        $this->deductionRepository->method('find')->willReturn($deduction);

        $dto = $this->service->getDeductionById(1);

        $this->assertSame('40.00', $dto->montant);
        $this->assertSame(DeductionType::MUTUELLE, $dto->typeDeduction);
    }

    // ── createDeduction ───────────────────────────────────────────────────

    public function testCreateDeductionLanceExceptionSiEmployeIntrouvable(): void
    {
        $this->employeeRepository->method('find')->willReturn(null);

        $this->expectException(EmployeeNotFoundException::class);
        $this->service->createDeduction($this->createValidDto(99));
    }

    public function testCreateDeductionLanceDomainExceptionSiTypeNull(): void
    {
        $employee = $this->createEmployeeMock();
        $this->employeeRepository->method('find')->willReturn($employee);

        $dto = new DeductionRequestDTO(1, null, '50.00', new \DateTime('2025-05-15'));
        $this->expectException(\DomainException::class);
        $this->service->createDeduction($dto);
    }

    public function testCreateDeductionLanceDomainExceptionSiMontantZero(): void
    {
        $employee = $this->createEmployeeMock();
        $this->employeeRepository->method('find')->willReturn($employee);

        $dto = new DeductionRequestDTO(1, DeductionType::MUTUELLE, '0', new \DateTime('2025-05-15'));
        $this->expectException(\DomainException::class);
        $this->service->createDeduction($dto);
    }

    public function testCreateDeductionLanceExceptionSiMontantHorsPlage(): void
    {
        $employee = $this->createEmployeeMock();
        $this->employeeRepository->method('find')->willReturn($employee);

        // Assurance Mutuelle max is 80 → 200 is too high
        $dto = new DeductionRequestDTO(1, DeductionType::MUTUELLE, '200', new \DateTime('2025-05-15'));
        $this->expectException(InvalidCompensationAmountException::class);
        $this->service->createDeduction($dto);
    }

    public function testCreateDeductionLanceDomainExceptionSiPeriodeVerrouillee(): void
    {
        $employee = $this->createEmployeeMock();
        $this->employeeRepository->method('find')->willReturn($employee);
        $this->fichePaieService->method('isPeriodLocked')->willReturn(true);

        $this->expectException(\DomainException::class);
        $this->service->createDeduction($this->createValidDto());
    }

    public function testCreateDeductionSuccesPersistEtRetourneDto(): void
    {
        $employee = $this->createEmployeeMock(1);
        $this->employeeRepository->method('find')->willReturn($employee);
        $this->fichePaieService->method('isPeriodLocked')->willReturn(false);

        // Inject ID on persist
        $this->em->method('persist')->willReturnCallback(function (Deduction $d): void {
            $r = new \ReflectionProperty(Deduction::class, 'id');
            $r->setValue($d, 20);
        });
        $this->em->expects($this->once())->method('flush');

        $dto = $this->service->createDeduction($this->createValidDto());

        $this->assertSame('50', $dto->montant);
        $this->assertSame(DeductionType::MUTUELLE, $dto->typeDeduction);
    }

    public function testCreateDeductionIgnoreLimitesPourTypeAvanceSalaire(): void
    {
        $employee = $this->createEmployeeMock(1);
        $this->employeeRepository->method('find')->willReturn($employee);
        $this->fichePaieService->method('isPeriodLocked')->willReturn(false);

        $this->em->method('persist')->willReturnCallback(function (Deduction $d): void {
            $r = new \ReflectionProperty(Deduction::class, 'id');
            $r->setValue($d, 21);
        });

        // Avance sur Salaire has max=0 → any amount is accepted
        $dto = new DeductionRequestDTO(1, DeductionType::AVANCE_SALAIRE, '5000', new \DateTime('2025-05-15'));
        $result = $this->service->createDeduction($dto);

        $this->assertSame('5000', $result->montant);
    }

    // ── updateDeduction ───────────────────────────────────────────────────

    public function testUpdateDeductionLanceDomainExceptionSiAbsent(): void
    {
        $this->deductionRepository->method('find')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->service->updateDeduction(99, $this->createValidDto());
    }

    public function testUpdateDeductionLanceDomainExceptionSiMontantNonPositif(): void
    {
        $employee  = $this->createEmployeeMock();
        $deduction = $this->createDeductionMock($employee);
        $this->deductionRepository->method('find')->willReturn($deduction);

        $dto = new DeductionRequestDTO(1, DeductionType::MUTUELLE, '-30', new \DateTime('2025-05-15'));
        $this->expectException(\DomainException::class);
        $this->service->updateDeduction(1, $dto);
    }

    public function testUpdateDeductionSuccesFlushEtRetourneDto(): void
    {
        $employee  = $this->createEmployeeMock();
        $deduction = $this->createDeductionEntity($employee);
        $this->deductionRepository->method('find')->willReturn($deduction);
        $this->em->expects($this->once())->method('flush');

        $dto    = $this->createValidDto(1, 60.0);
        $result = $this->service->updateDeduction(1, $dto);

        $this->assertSame('60', $result->montant);
    }

    public function testUpdateDeductionRecalculeAnciennePeriodeSiDateChangee(): void
    {
        $employee  = $this->createEmployeeMock();
        $deduction = $this->createDeductionEntity($employee);
        // deduction is from May 2025, new date is June 2025
        $this->deductionRepository->method('find')->willReturn($deduction);

        $this->fichePaieService->expects($this->exactly(2))->method('recalculateTotals');

        $dto = new DeductionRequestDTO(1, DeductionType::MUTUELLE, '50', new \DateTime('2025-06-15'));
        $this->service->updateDeduction(1, $dto);
    }

    // ── deleteDeduction ───────────────────────────────────────────────────

    public function testDeleteDeductionLanceDomainExceptionSiAbsent(): void
    {
        $this->deductionRepository->method('find')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->service->deleteDeduction(99);
    }

    public function testDeleteDeductionSuccesSupprimeEtFlush(): void
    {
        $employee  = $this->createEmployeeMock();
        $deduction = $this->createDeductionMock($employee);
        $this->deductionRepository->method('find')->willReturn($deduction);

        $this->em->expects($this->once())->method('remove')->with($deduction);
        $this->em->expects($this->once())->method('flush');

        $this->service->deleteDeduction(1);
    }

    public function testDeleteDeductionRecalculeTotalsApresSuppression(): void
    {
        $employee  = $this->createEmployeeMock();
        $deduction = $this->createDeductionMock($employee, 1, '50.00');
        $this->deductionRepository->method('find')->willReturn($deduction);

        $this->fichePaieService->expects($this->once())->method('recalculateTotals')
            ->with(1, 5, 2025);

        $this->service->deleteDeduction(1);
    }
}
