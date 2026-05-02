<?php

namespace App\Tests\Service\Remeneration;

use App\DTO\Payroll\FichePaieRequestDTO;
use App\Entity\Paie\FichePaie;
use App\Entity\Rh\Employee;
use App\Exception\Payroll\DuplicateFichePaieException;
use App\Exception\Payroll\EmployeeNotFoundException;
use App\Exception\Payroll\FichePaieNotFoundException;
use App\Exception\Payroll\InvalidPeriodException;
use App\Exception\Payroll\InvalidSalaryException;
use App\Repository\Paie\DeductionRepository;
use App\Repository\Paie\FichePaieRepository;
use App\Repository\Paie\PrimeRepository;
use App\Repository\Rh\EmployeeRepository;
use App\Service\Paie\FichePaieService;
use App\Service\Shared\CachingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class FichePaieServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private FichePaieRepository&MockObject    $fichePaieRepository;
    private PrimeRepository&MockObject        $primeRepository;
    private DeductionRepository&MockObject    $deductionRepository;
    private EmployeeRepository&MockObject     $employeeRepository;
    private CachingService&MockObject         $cachingService;
    private FichePaieService                  $service;

    protected function setUp(): void
    {
        $this->em                   = $this->createMock(EntityManagerInterface::class);
        $this->fichePaieRepository  = $this->createMock(FichePaieRepository::class);
        $this->primeRepository      = $this->createMock(PrimeRepository::class);
        $this->deductionRepository  = $this->createMock(DeductionRepository::class);
        $this->employeeRepository   = $this->createMock(EmployeeRepository::class);
        $this->cachingService       = $this->createMock(CachingService::class);

        $this->service = new FichePaieService(
            $this->em,
            $this->fichePaieRepository,
            $this->primeRepository,
            $this->deductionRepository,
            $this->employeeRepository,
            $this->cachingService,
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function createEmployeeMock(int $id = 1, int $rhId = 10): Employee
    {
        $employee = $this->createMock(Employee::class);
        $employee->method('getId')->willReturn($id);
        $employee->method('getRhId')->willReturn($rhId);
        $employee->method('getFirstName')->willReturn('Alice');
        $employee->method('getLastName')->willReturn('Dupont');
        return $employee;
    }

    /**
     * Creates a mock FichePaie (as returned from repository).
     */
    private function createFicheMock(
        Employee $employee,
        int $id = 1,
        int $mois = 5,
        int $annee = 2025,
        string $brut = '1200.00',
        bool $paid = false,
    ): FichePaie {
        $fiche = $this->createMock(FichePaie::class);
        $fiche->method('getId')->willReturn($id);
        $fiche->method('getEmployee')->willReturn($employee);
        $fiche->method('getMois')->willReturn($mois);
        $fiche->method('getAnnee')->willReturn($annee);
        $fiche->method('getSalaireBrut')->willReturn($brut);
        $fiche->method('getTotalPrimes')->willReturn('0.00');
        $fiche->method('getTotalDeductions')->willReturn('0.00');
        $fiche->method('getSalaireNet')->willReturn($brut);
        $fiche->method('isStatutPaiement')->willReturn($paid);
        $fiche->method('getNotes')->willReturn(null);
        $fiche->method('getCreatedAt')->willReturn(null);
        $fiche->method('getUpdatedAt')->willReturn(null);
        return $fiche;
    }

    /**
     * Creates a real FichePaie entity and injects an ID via reflection.
     */
    private function createFicheEntity(Employee $employee, int $id = 1, int $mois = 5, int $annee = 2025, string $brut = '1200.00'): FichePaie
    {
        $fiche = new FichePaie();
        $fiche->setEmployee($employee)
            ->setMois($mois)
            ->setAnnee($annee)
            ->setSalaireBrut($brut)
            ->setTotalPrimes('0.00')
            ->setTotalDeductions('0.00');
        $fiche->calculateSalaireNet();

        $r = new \ReflectionProperty(FichePaie::class, 'id');
        $r->setValue($fiche, $id);

        return $fiche;
    }

    // ── getFichePaieById ─────────────────────────────────────────────────

    public function testGetFichePaieByIdLanceFichePaieNotFoundExceptionSiAbsent(): void
    {
        $this->fichePaieRepository->method('find')->willReturn(null);

        $this->expectException(FichePaieNotFoundException::class);
        $this->service->getFichePaieById(99);
    }

    public function testGetFichePaieByIdRetourneDtoSiTrouve(): void
    {
        $employee = $this->createEmployeeMock();
        $fiche    = $this->createFicheMock($employee, 1, 5, 2025, '1500.00');
        $this->fichePaieRepository->method('find')->willReturn($fiche);

        $dto = $this->service->getFichePaieById(1);

        $this->assertSame('1500.00', $dto->salaireBrut);
        $this->assertSame(5, $dto->mois);
    }

    // ── createFichePaie ──────────────────────────────────────────────────

    public function testCreateFichePaieLanceExceptionSiMoisInvalide(): void
    {
        $this->expectException(InvalidPeriodException::class);
        $this->service->createFichePaie(new FichePaieRequestDTO(1, 13, 2025, '1200.00'));
    }

    public function testCreateFichePaieLanceExceptionSiAnneeInvalide(): void
    {
        $this->expectException(InvalidPeriodException::class);
        $this->service->createFichePaie(new FichePaieRequestDTO(1, 5, 1999, '1200.00'));
    }

    public function testCreateFichePaieLanceExceptionSiSalaireNegatif(): void
    {
        $this->expectException(InvalidSalaryException::class);
        $this->service->createFichePaie(new FichePaieRequestDTO(1, 5, 2025, '-100.00'));
    }

    public function testCreateFichePaieLanceExceptionSiSalaireInferieurSmig(): void
    {
        $this->expectException(InvalidSalaryException::class);
        // 100 DT < 450 DT SMIG
        $this->service->createFichePaie(new FichePaieRequestDTO(1, 5, 2025, '100.00'));
    }

    public function testCreateFichePaieLanceExceptionSiEmployeIntrouvable(): void
    {
        $this->employeeRepository->method('find')->willReturn(null);

        $this->expectException(EmployeeNotFoundException::class);
        $this->service->createFichePaie(new FichePaieRequestDTO(99, 5, 2025, '1200.00'));
    }

    public function testCreateFichePaieLanceDuplicateExceptionSiPeriodeDejaPresente(): void
    {
        $employee = $this->createEmployeeMock();
        $this->employeeRepository->method('find')->willReturn($employee);

        $existing = $this->createFicheMock($employee);
        $this->fichePaieRepository->method('findByEmployeeAndPeriodSingle')->willReturn($existing);

        $this->expectException(DuplicateFichePaieException::class);
        $this->service->createFichePaie(new FichePaieRequestDTO(1, 5, 2025, '1200.00'));
    }

    public function testCreateFichePaieSuccesPersistEtRetourneDto(): void
    {
        $employee = $this->createEmployeeMock(1, 10);
        $this->employeeRepository->method('find')->willReturn($employee);
        $this->fichePaieRepository->method('findByEmployeeAndPeriodSingle')->willReturn(null);
        $this->primeRepository->method('getTotalByEmployeeAndPeriod')->willReturn('200.00');
        $this->deductionRepository->method('getTotalByEmployeeAndPeriod')->willReturn('50.00');

        // Set ID on the entity when persist() is called so DTO can be built
        $this->em->method('persist')->willReturnCallback(function (FichePaie $fiche): void {
            $r = new \ReflectionProperty(FichePaie::class, 'id');
            $r->setValue($fiche, 42);
        });
        $this->em->expects($this->once())->method('flush');

        $dto = $this->service->createFichePaie(new FichePaieRequestDTO(1, 5, 2025, '1200.00'));

        $this->assertSame('1200.00', $dto->salaireBrut);
        $this->assertSame('200.00', $dto->totalPrimes);
        $this->assertSame('50.00', $dto->totalDeductions);
        // salaireNet = 1200 + 200 - 50 = 1350
        $this->assertSame('1350.00', $dto->salaireNet);
    }

    // ── updateFichePaie ──────────────────────────────────────────────────

    public function testUpdateFichePaieLanceExceptionSiFicheAbsente(): void
    {
        $this->fichePaieRepository->method('find')->willReturn(null);

        $this->expectException(FichePaieNotFoundException::class);
        $this->service->updateFichePaie(99, new FichePaieRequestDTO(1, 5, 2025, '1500.00'));
    }

    public function testUpdateFichePaieSuccesFlushEtRetourneDto(): void
    {
        $employee = $this->createEmployeeMock(1, 10);
        $fiche    = $this->createFicheEntity($employee, 1, 4, 2025, '1000.00');
        $this->fichePaieRepository->method('find')->willReturn($fiche);
        $this->primeRepository->method('getTotalByEmployeeAndPeriod')->willReturn('0.00');
        $this->deductionRepository->method('getTotalByEmployeeAndPeriod')->willReturn('0.00');

        $this->em->expects($this->once())->method('flush');

        $dto = $this->service->updateFichePaie(1, new FichePaieRequestDTO(1, 5, 2025, '1500.00'));

        $this->assertSame('1500.00', $dto->salaireBrut);
        $this->assertSame(5, $dto->mois);
        $this->assertSame(2025, $dto->annee);
    }

    // ── deleteFichePaie ──────────────────────────────────────────────────

    public function testDeleteFichePaieLanceExceptionSiFicheAbsente(): void
    {
        $this->fichePaieRepository->method('find')->willReturn(null);

        $this->expectException(FichePaieNotFoundException::class);
        $this->service->deleteFichePaie(99);
    }

    public function testDeleteFichePaieSuccesSupprimeEntiteEtFlush(): void
    {
        $employee = $this->createEmployeeMock();
        $fiche    = $this->createFicheMock($employee);
        $this->fichePaieRepository->method('find')->willReturn($fiche);

        $this->em->expects($this->once())->method('remove')->with($fiche);
        $this->em->expects($this->once())->method('flush');

        $this->service->deleteFichePaie(1);
    }

    // ── toggleStatutPaiement ─────────────────────────────────────────────

    public function testToggleStatutPaiementLanceExceptionSiFicheAbsente(): void
    {
        $this->fichePaieRepository->method('find')->willReturn(null);

        $this->expectException(FichePaieNotFoundException::class);
        $this->service->toggleStatutPaiement(99);
    }

    public function testToggleStatutPaiementBasculeFalseVersTrue(): void
    {
        $employee = $this->createEmployeeMock();
        $fiche    = $this->createFicheEntity($employee);  // statutPaiement = false by default
        $this->fichePaieRepository->method('find')->willReturn($fiche);
        $this->em->expects($this->once())->method('flush');

        $this->assertFalse($fiche->isStatutPaiement());

        $result = $this->service->toggleStatutPaiement(1);

        $this->assertTrue($fiche->isStatutPaiement());
        $this->assertTrue($result->statutPaiement);
    }

    public function testToggleStatutPaiementBasculeTrueVersFalse(): void
    {
        $employee = $this->createEmployeeMock();
        $fiche    = $this->createFicheEntity($employee);
        $fiche->setStatutPaiement(true);
        $this->fichePaieRepository->method('find')->willReturn($fiche);
        $this->em->method('flush');

        $result = $this->service->toggleStatutPaiement(1);

        $this->assertFalse($fiche->isStatutPaiement());
        $this->assertFalse($result->statutPaiement);
    }

    // ── isPeriodLocked ────────────────────────────────────────────────────

    public function testIsPeriodLockedRetourneFalseQuandFicheAbsente(): void
    {
        $this->fichePaieRepository->method('findByEmployeeAndPeriodSingle')->willReturn(null);

        $this->assertFalse($this->service->isPeriodLocked(1, 5, 2025));
    }

    public function testIsPeriodLockedRetourneFalseQuandFicheNonPayee(): void
    {
        $employee = $this->createEmployeeMock();
        $fiche    = $this->createFicheEntity($employee);  // paid = false
        $this->fichePaieRepository->method('findByEmployeeAndPeriodSingle')->willReturn($fiche);

        $this->assertFalse($this->service->isPeriodLocked(1, 5, 2025));
    }

    public function testIsPeriodLockedRetourneTrueQuandFichePayee(): void
    {
        $employee = $this->createEmployeeMock();
        $fiche    = $this->createFicheEntity($employee);
        $fiche->setStatutPaiement(true);
        $this->fichePaieRepository->method('findByEmployeeAndPeriodSingle')->willReturn($fiche);

        $this->assertTrue($this->service->isPeriodLocked(1, 5, 2025));
    }

    // ── recalculateTotals ─────────────────────────────────────────────────

    public function testRecalculateTotalsNeFaitRienSiFicheAbsente(): void
    {
        $this->fichePaieRepository->method('findByEmployeeAndPeriodSingle')->willReturn(null);
        $this->em->expects($this->never())->method('flush');

        $this->service->recalculateTotals(1, 5, 2025);
    }

    public function testRecalculateTotalsRecalculeNetEtFlushSiFichePresente(): void
    {
        $employee = $this->createEmployeeMock(1, 10);
        $fiche    = $this->createFicheEntity($employee, 1, 5, 2025, '2000.00');

        $this->fichePaieRepository->method('findByEmployeeAndPeriodSingle')->willReturn($fiche);
        $this->primeRepository->method('getTotalByEmployeeAndPeriod')->willReturn('300.00');
        $this->deductionRepository->method('getTotalByEmployeeAndPeriod')->willReturn('100.00');
        $this->em->expects($this->once())->method('flush');

        $this->service->recalculateTotals(1, 5, 2025);

        $this->assertSame('300.00', $fiche->getTotalPrimes());
        $this->assertSame('100.00', $fiche->getTotalDeductions());
        // net = 2000 + 300 - 100 = 2200
        $this->assertSame('2200.00', $fiche->getSalaireNet());
    }
}
