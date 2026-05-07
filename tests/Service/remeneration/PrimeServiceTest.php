<?php

namespace App\Tests\Service\Remeneration;

use App\DTO\Payroll\PrimeRequestDTO;
use App\Entity\Paie\Prime;
use App\Entity\Rh\Employee;
use App\Enum\PrimeType;
use App\Exception\Payroll\EmployeeNotFoundException;
use App\Exception\Payroll\InvalidCompensationAmountException;
use App\Repository\Paie\PrimeRepository;
use App\Repository\Rh\EmployeeRepository;
use App\Service\Paie\CompensationValidationService;
use App\Service\Paie\FichePaieService;
use App\Service\Paie\PrimeService;
use App\Service\Shared\CachingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class PrimeServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private PrimeRepository&MockObject        $primeRepository;
    private EmployeeRepository&MockObject     $employeeRepository;
    private CachingService&MockObject         $cachingService;
    private FichePaieService&MockObject       $fichePaieService;
    private PrimeService                      $service;

    protected function setUp(): void
    {
        $this->em                 = $this->createMock(EntityManagerInterface::class);
        $this->primeRepository    = $this->createMock(PrimeRepository::class);
        $this->employeeRepository = $this->createMock(EmployeeRepository::class);
        $this->cachingService     = $this->createMock(CachingService::class);
        $this->fichePaieService   = $this->createMock(FichePaieService::class);

        // Use the real CompensationValidationService (no deps, not mocking needed)
        $validationService = new CompensationValidationService();

        $this->service = new PrimeService(
            $this->em,
            $this->primeRepository,
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
        $employee->method('getFirstName')->willReturn('Alice');
        $employee->method('getLastName')->willReturn('Dupont');
        return $employee;
    }

    /**
     * Creates a mock Prime (as returned from repository).
     */
    private function createPrimeMock(Employee $employee, int $id = 1, string $montant = '200.00'): Prime
    {
        $date  = new \DateTimeImmutable('2025-05-15');
        $prime = $this->createMock(Prime::class);
        $prime->method('getId')->willReturn($id);
        $prime->method('getEmployee')->willReturn($employee);
        $prime->method('getTypePrime')->willReturn(PrimeType::BONUS);
        $prime->method('getMontant')->willReturn($montant);
        $prime->method('getDateAttribution')->willReturn($date);
        $prime->method('getMotif')->willReturn(null);
        $prime->method('getCreatedAt')->willReturn(null);
        $prime->method('getUpdatedAt')->willReturn(null);
        $prime->method('getMonth')->willReturn(5);
        $prime->method('getYear')->willReturn(2025);
        return $prime;
    }

    /**
     * Creates a real Prime entity with ID set via reflection.
     */
    private function createPrimeEntity(Employee $employee, int $id = 1): Prime
    {
        $prime = new Prime();
        $prime->setEmployee($employee)
            ->setTypePrime(PrimeType::BONUS)
            ->setMontant('200.00')
            ->setDateAttribution(new \DateTime('2025-05-15'))
            ->setMotif('Test');

        $r = new \ReflectionProperty(Prime::class, 'id');
        $r->setValue($prime, $id);

        return $prime;
    }

    /**
     * Creates a valid PrimeRequestDTO (Bonus: 100-300 range).
     */
    private function createValidDto(int $employeeId = 1, float $montant = 200.0): PrimeRequestDTO
    {
        return new PrimeRequestDTO(
            $employeeId,
            PrimeType::BONUS,
            (string) $montant,
            new \DateTime('2025-05-15'),
            'Bonus annuel',
        );
    }

    // ── getPrimeById ──────────────────────────────────────────────────────

    public function testGetPrimeByIdLanceDomainExceptionSiAbsent(): void
    {
        $this->primeRepository->method('find')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->service->getPrimeById(99);
    }

    public function testGetPrimeByIdRetourneDtoSiTrouve(): void
    {
        $employee = $this->createEmployeeMock();
        $prime    = $this->createPrimeMock($employee, 1, '150.00');
        $this->primeRepository->method('find')->willReturn($prime);

        $dto = $this->service->getPrimeById(1);

        $this->assertSame('150.00', $dto->montant);
        $this->assertSame(PrimeType::BONUS, $dto->typePrime);
    }

    // ── createPrime ───────────────────────────────────────────────────────

    public function testCreatePrimeLanceExceptionSiEmployeIntrouvable(): void
    {
        $this->employeeRepository->method('find')->willReturn(null);

        $this->expectException(EmployeeNotFoundException::class);
        $this->service->createPrime($this->createValidDto(99));
    }

    public function testCreatePrimeLanceDomainExceptionSiTypePrimeNull(): void
    {
        $employee = $this->createEmployeeMock();
        $this->employeeRepository->method('find')->willReturn($employee);

        $dto = new PrimeRequestDTO(1, null, '200.00', new \DateTime('2025-05-15'));
        $this->expectException(\DomainException::class);
        $this->service->createPrime($dto);
    }

    public function testCreatePrimeLanceDomainExceptionSiMontantZero(): void
    {
        $employee = $this->createEmployeeMock();
        $this->employeeRepository->method('find')->willReturn($employee);

        $dto = new PrimeRequestDTO(1, PrimeType::BONUS, '0', new \DateTime('2025-05-15'));
        $this->expectException(\DomainException::class);
        $this->service->createPrime($dto);
    }

    public function testCreatePrimeLanceExceptionSiMontantHorsPlageBonus(): void
    {
        $employee = $this->createEmployeeMock();
        $this->employeeRepository->method('find')->willReturn($employee);

        // Bonus max is 300 → 500 is too high
        $dto = new PrimeRequestDTO(1, PrimeType::BONUS, '500', new \DateTime('2025-05-15'));
        $this->expectException(InvalidCompensationAmountException::class);
        $this->service->createPrime($dto);
    }

    public function testCreatePrimeLanceDomainExceptionSiPeriodeVerrouillee(): void
    {
        $employee = $this->createEmployeeMock();
        $this->employeeRepository->method('find')->willReturn($employee);
        $this->fichePaieService->method('isPeriodLocked')->willReturn(true);

        $this->expectException(\DomainException::class);
        $this->service->createPrime($this->createValidDto());
    }

    public function testCreatePrimeSuccesPersistEtRetourneDto(): void
    {
        $employee = $this->createEmployeeMock(1);
        $this->employeeRepository->method('find')->willReturn($employee);
        $this->fichePaieService->method('isPeriodLocked')->willReturn(false);

        // Inject ID on persist
        $this->em->method('persist')->willReturnCallback(function (Prime $p): void {
            $r = new \ReflectionProperty(Prime::class, 'id');
            $r->setValue($p, 10);
        });
        $this->em->expects($this->once())->method('flush');

        $dto = $this->service->createPrime($this->createValidDto());

        $this->assertSame('200', $dto->montant);
        $this->assertSame(PrimeType::BONUS, $dto->typePrime);
    }

    // ── updatePrime ───────────────────────────────────────────────────────

    public function testUpdatePrimeLanceDomainExceptionSiAbsent(): void
    {
        $this->primeRepository->method('find')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->service->updatePrime(99, $this->createValidDto());
    }

    public function testUpdatePrimeLanceDomainExceptionSiMontantNonPositif(): void
    {
        $employee = $this->createEmployeeMock();
        $prime    = $this->createPrimeMock($employee);
        $this->primeRepository->method('find')->willReturn($prime);

        $dto = new PrimeRequestDTO(1, PrimeType::BONUS, '-50', new \DateTime('2025-05-15'));
        $this->expectException(\DomainException::class);
        $this->service->updatePrime(1, $dto);
    }

    public function testUpdatePrimeSuccesFlushEtRetourneDto(): void
    {
        $employee = $this->createEmployeeMock();
        $prime    = $this->createPrimeEntity($employee);
        $this->primeRepository->method('find')->willReturn($prime);
        $this->em->expects($this->once())->method('flush');

        $dto = $this->createValidDto(1, 250.0);
        $result = $this->service->updatePrime(1, $dto);

        $this->assertSame('250', $result->montant);
    }

    public function testUpdatePrimeRecalculeAnciennePeriodeSiDateChangee(): void
    {
        $employee = $this->createEmployeeMock();
        $prime    = $this->createPrimeEntity($employee);
        // prime is from May 2025, new date is June 2025
        $this->primeRepository->method('find')->willReturn($prime);

        $this->fichePaieService->expects($this->exactly(2))->method('recalculateTotals');

        $dto = new PrimeRequestDTO(1, PrimeType::BONUS, '200', new \DateTime('2025-06-15'));
        $this->service->updatePrime(1, $dto);
    }

    // ── deletePrime ───────────────────────────────────────────────────────

    public function testDeletePrimeLanceDomainExceptionSiAbsent(): void
    {
        $this->primeRepository->method('find')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->service->deletePrime(99);
    }

    public function testDeletePrimeSuccesSupprimeEtFlush(): void
    {
        $employee = $this->createEmployeeMock();
        $prime    = $this->createPrimeMock($employee);
        $this->primeRepository->method('find')->willReturn($prime);

        $this->em->expects($this->once())->method('remove')->with($prime);
        $this->em->expects($this->once())->method('flush');

        $this->service->deletePrime(1);
    }

    public function testDeletePrimeRecalculeTotalsApresSuppressionn(): void
    {
        $employee = $this->createEmployeeMock();
        $prime    = $this->createPrimeMock($employee, 1, '200.00');
        $this->primeRepository->method('find')->willReturn($prime);

        $this->fichePaieService->expects($this->once())->method('recalculateTotals')
            ->with(1, 5, 2025);

        $this->service->deletePrime(1);
    }
}
