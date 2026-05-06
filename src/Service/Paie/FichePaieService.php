<?php

namespace App\Service\Paie;

use App\DTO\Payroll\FichePaieRequestDTO;
use App\DTO\Payroll\FichePaieResponseDTO;
use App\DTO\Payroll\PayrollStatsDTO;
use App\Entity\Paie\FichePaie;
use App\Exception\Payroll\DuplicateFichePaieException;
use App\Exception\Payroll\EmployeeNotFoundException;
use App\Exception\Payroll\FichePaieNotFoundException;
use App\Exception\Payroll\InvalidPeriodException;
use App\Exception\Payroll\InvalidSalaryException;
use App\Repository\Paie\FichePaieRepository;
use App\Repository\Paie\PrimeRepository;
use App\Repository\Paie\DeductionRepository;
use App\Repository\Rh\EmployeeRepository;
use App\Service\Shared\CachingService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * FichePaieService - Business logic for pay slip management
 */
class FichePaieService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FichePaieRepository $fichePaieRepository,
        private readonly PrimeRepository $primeRepository,
        private readonly DeductionRepository $deductionRepository,
        private readonly EmployeeRepository $employeeRepository,
        private readonly CachingService $cachingService,
    ) {
    }

    /**
     * Get all pay slips for an RH
     *
     * @return FichePaieResponseDTO[]
     */
    public function getFichePaiesByRh(int $rhId): array
    {
        $fiches = $this->fichePaieRepository->findByRh($rhId);
        return array_map(fn(FichePaie $f) => new FichePaieResponseDTO($f), $fiches);
    }

    /**
     * Search pay slips with filters
     *
     * @return FichePaieResponseDTO[]
     */
    public function searchFichePaies(
        int $rhId,
        string $employeeSearch = '',
        string $periodSearch = '',
        string $sort = 'createdAt-DESC'
    ): array {
        $fiches = $this->fichePaieRepository->findByRhAndSearch($rhId, $employeeSearch, $periodSearch, $sort);
        return array_map(fn(FichePaie $f) => new FichePaieResponseDTO($f), $fiches);
    }

    /**
     * Get pay slip by ID
     */
    public function getFichePaieById(int $id): FichePaieResponseDTO
    {
        $fichePaie = $this->fichePaieRepository->find($id);
        if (!$fichePaie) {
            throw FichePaieNotFoundException::withId($id);
        }
        return new FichePaieResponseDTO($fichePaie);
    }

    /**
     * Create a new pay slip
     */
    public function createFichePaie(FichePaieRequestDTO $dto): FichePaieResponseDTO
    {
        $this->validatePeriod($dto->mois, $dto->annee);
        $this->validateSalary($dto->salaireBrut);

        if ($dto->employeeId === null) {
            throw new \DomainException('Employee ID is required');
        }
        if ($dto->mois === null || $dto->annee === null) {
            throw new \DomainException('Month and year are required');
        }
        if ($dto->salaireBrut === null) {
            throw new \DomainException('Gross salary is required');
        }
        $employee = $this->employeeRepository->find($dto->employeeId);
        if (!$employee) {
            throw EmployeeNotFoundException::withId($dto->employeeId);
        }

        // Check for duplicates
        $existing = $this->fichePaieRepository->findByEmployeeAndPeriodSingle(
            $dto->employeeId,
            $dto->mois,
            $dto->annee
        );
        if ($existing !== null) {
            throw DuplicateFichePaieException::forEmployeeAndPeriod($dto->employeeId, $dto->mois, $dto->annee);
        }

        // Calculate totals from primes and deductions
        $totalPrimes = $this->primeRepository->getTotalByEmployeeAndPeriod(
            $dto->employeeId,
            $dto->mois,
            $dto->annee
        );
        $totalDeductions = $this->deductionRepository->getTotalByEmployeeAndPeriod(
            $dto->employeeId,
            $dto->mois,
            $dto->annee
        );

        $fichePaie = new FichePaie();
        $fichePaie->setEmployee($employee)
            ->setMois($dto->mois)
            ->setAnnee($dto->annee)
            ->setSalaireBrut($dto->salaireBrut)
            ->setTotalPrimes($totalPrimes)
            ->setTotalDeductions($totalDeductions)
            ->setNotes($dto->notes);

        $fichePaie->calculateSalaireNet();

        $this->em->persist($fichePaie);
        $this->em->flush();

        // Invalidate cache
        $rhId = $employee->getRhId();
        if ($rhId !== null) {
            $this->cachingService->forget(CachingService::payrollStatsKey($rhId));
        }
        $this->cachingService->forget(CachingService::employeeFichesKey($dto->employeeId));

        return new FichePaieResponseDTO($fichePaie);
    }

    /**
     * Update an existing pay slip
     */
    public function updateFichePaie(int $id, FichePaieRequestDTO $dto): FichePaieResponseDTO
    {
        $fichePaie = $this->fichePaieRepository->find($id);
        if (!$fichePaie) {
            throw FichePaieNotFoundException::withId($id);
        }

        $this->validatePeriod($dto->mois, $dto->annee);
        $this->validateSalary($dto->salaireBrut);

        if ($dto->mois === null || $dto->annee === null || $dto->salaireBrut === null) {
            throw new \DomainException('Missing required fields');
        }

        $employeeEntity = $fichePaie->getEmployee();
        $employeeId = $employeeEntity !== null ? $employeeEntity->getId() : null;

        // Recalculate totals
        $totalPrimes = $this->primeRepository->getTotalByEmployeeAndPeriod(
            (int) $employeeId,
            $dto->mois,
            $dto->annee
        );
        $totalDeductions = $this->deductionRepository->getTotalByEmployeeAndPeriod(
            (int) $employeeId,
            $dto->mois,
            $dto->annee
        );

        $fichePaie->setMois($dto->mois)
            ->setAnnee($dto->annee)
            ->setSalaireBrut($dto->salaireBrut)
            ->setTotalPrimes($totalPrimes)
            ->setTotalDeductions($totalDeductions)
            ->setNotes($dto->notes);

        $fichePaie->calculateSalaireNet();

        $this->em->flush();

        // Invalidate cache
        $rhId = $employeeEntity?->getRhId();
        if ($rhId !== null) {
            $this->cachingService->forget(CachingService::payrollStatsKey($rhId));
        }
        if ($employeeId !== null) {
            $this->cachingService->forget(CachingService::employeeFichesKey($employeeId));
        }

        return new FichePaieResponseDTO($fichePaie);
    }

    /**
     * Delete a pay slip
     */
    public function deleteFichePaie(int $id): void
    {
        $fichePaie = $this->fichePaieRepository->find($id);
        if (!$fichePaie) {
            throw FichePaieNotFoundException::withId($id);
        }

        $employeeEntity = $fichePaie->getEmployee();
        $employeeId = $employeeEntity?->getId();
        $rhId = $employeeEntity?->getRhId();

        $this->em->remove($fichePaie);
        $this->em->flush();

        // Invalidate cache
        if ($rhId !== null) {
            $this->cachingService->forget(CachingService::payrollStatsKey($rhId));
        }
        if ($employeeId !== null) {
            $this->cachingService->forget(CachingService::employeeFichesKey($employeeId));
        }
    }

    /**
     * Get payroll statistics for an RH
     */
    public function getStatsByRh(int $rhId): PayrollStatsDTO
    {
        $cacheKey = CachingService::payrollStatsKey($rhId);

        return $this->cachingService->remember($cacheKey, function () use ($rhId) {
            $stats = $this->fichePaieRepository->getStatsByRh($rhId);

            return new PayrollStatsDTO(
                totalSalaireBrut: $stats['totalBrut'] ?? '0.00',
                totalPrimes: $stats['totalPrimes'] ?? '0.00',
                totalDeductions: $stats['totalDeductions'] ?? '0.00',
                totalSalaireNet: $stats['totalNet'] ?? '0.00',
                fichesPaieCount: (int) ($stats['totalFiches'] ?? 0),
            );
        });
    }

    /**
     * Get all pay slips for an employee
     *
     * @return FichePaieResponseDTO[]
     */
    public function getFichePaiesByEmployee(int $employeeId): array
    {
        $cacheKey = CachingService::employeeFichesKey($employeeId);

        return $this->cachingService->remember($cacheKey, function () use ($employeeId) {
            $fiches = $this->fichePaieRepository->findBy(
                ['employee' => $employeeId],
                ['annee' => 'DESC', 'mois' => 'DESC']
            );
            return array_map(fn(FichePaie $f) => new FichePaieResponseDTO($f), $fiches);
        });
    }

    /**
     * Recalculate totalPrimes, totalDeductions and salaireNet for a given employee+period.
     * Called automatically by PrimeService and DeductionService after any mutation.
     */
    public function recalculateTotals(int $employeeId, int $mois, int $annee): void
    {
        $fichePaie = $this->fichePaieRepository->findByEmployeeAndPeriodSingle($employeeId, $mois, $annee);
        if (!$fichePaie) {
            return; // No fiche de paie for this period yet — nothing to recalculate
        }

        $totalPrimes = $this->primeRepository->getTotalByEmployeeAndPeriod($employeeId, $mois, $annee);
        $totalDeductions = $this->deductionRepository->getTotalByEmployeeAndPeriod($employeeId, $mois, $annee);

        $fichePaie->setTotalPrimes($totalPrimes)
            ->setTotalDeductions($totalDeductions);

        $fichePaie->calculateSalaireNet();

        $this->em->flush();

        // Invalidate cache
        $employee = $fichePaie->getEmployee();
        $employeeRhId = $employee?->getRhId();
        if ($employeeRhId !== null) {
            $this->cachingService->forget(CachingService::payrollStatsKey($employeeRhId));
        }
        $this->cachingService->forget(CachingService::employeeFichesKey($employeeId));
    }

    /**
     * Toggle payment status (payé / non payé) for a fiche de paie
     */
    public function toggleStatutPaiement(int $id): FichePaieResponseDTO
    {
        $fichePaie = $this->fichePaieRepository->find($id);
        if (!$fichePaie) {
            throw FichePaieNotFoundException::withId($id);
        }

        $fichePaie->setStatutPaiement(!$fichePaie->isStatutPaiement());
        $this->em->flush();

        $empId = $fichePaie->getEmployee()?->getId();
        if ($empId !== null) {
            $this->cachingService->forget(CachingService::employeeFichesKey($empId));
        }

        return new FichePaieResponseDTO($fichePaie);
    }

    /**
     * Refresh pay slip totals from primes and deductions
     */
    public function refreshFichePaieTotals(int $fichePaieId): FichePaieResponseDTO
    {
        $fichePaie = $this->fichePaieRepository->find($fichePaieId);
        if (!$fichePaie) {
            throw FichePaieNotFoundException::withId($fichePaieId);
        }

        $employee = $fichePaie->getEmployee();

        $this->recalculateTotals((int) $employee?->getId(), $fichePaie->getMois(), $fichePaie->getAnnee());

        // Re-fetch after recalculation
        $this->em->refresh($fichePaie);

        return new FichePaieResponseDTO($fichePaie);
    }

    /**
     * Validate period (month and year)
     *
     * @throws InvalidPeriodException
     */
    private function validatePeriod(?int $mois, ?int $annee): void
    {
        if ($mois === null || $mois < 1 || $mois > 12) {
            throw InvalidPeriodException::invalidMonth($mois ?? 0);
        }

        if ($annee === null || $annee < 2000 || $annee > 2100) {
            throw InvalidPeriodException::invalidYear($annee ?? 0);
        }
    }

    /**
     * Validate salary amount
     *
     * @throws InvalidSalaryException
     */
    private function validateSalary(?string $salaireBrut): void
    {
        if ($salaireBrut === null) {
            throw InvalidSalaryException::invalidFormat('null');
        }

        $brut = (float) $salaireBrut;
        if ($brut < 0) {
            throw InvalidSalaryException::negativeAmount($salaireBrut);
        }

        // SMIG Tunisie — minimum wage check
        if ($brut > 0 && $brut < 450.00) {
            throw new InvalidSalaryException(
                sprintf('Le salaire brut (%.2f DT) est inférieur au SMIG (450.00 DT).', $brut)
            );
        }
    }

    /**
     * Check if a period is locked (fiche already marked as paid).
     * Used to block adding primes/deductions to a locked period.
     */
    public function isPeriodLocked(int $employeeId, int $mois, int $annee): bool
    {
        $fiche = $this->fichePaieRepository->findByEmployeeAndPeriodSingle($employeeId, $mois, $annee);
        return $fiche !== null && $fiche->isStatutPaiement();
    }

    /**
     * Get monthly evolution for a given employee and year.
     * Returns an array of monthly payroll data for charts.
     *
     * @return array<int, array{mois: int, brut: string, primes: string, deductions: string, net: string}>
     */
    public function getMonthlyEvolution(int $employeeId, int $annee): array
    {
        $fiches = $this->fichePaieRepository->findBy(
            ['employee' => $employeeId, 'annee' => $annee],
            ['mois' => 'ASC']
        );

        return array_map(fn(FichePaie $f) => [
            'mois' => $f->getMois() ?? 0,
            'brut' => $f->getSalaireBrut() ?? '0.00',
            'primes' => $f->getTotalPrimes() ?? '0.00',
            'deductions' => $f->getTotalDeductions() ?? '0.00',
            'net' => $f->getSalaireNet() ?? '0.00',
        ], $fiches);
    }

    /**
     * Get global monthly stats for all employees managed by an RH.
     *
     * @return array<int, array{mois: int, annee: int, totalBrut: string, totalPrimes: string, totalDeductions: string, totalNet: string, count: int}>
     */
    public function getMonthlyStatsByRh(int $rhId, int $annee): array
    {
        $qb = $this->fichePaieRepository->createQueryBuilder('fp')
            ->select(
                'fp.mois',
                'SUM(fp.salaireBrut) as totalBrut',
                'SUM(fp.totalPrimes) as totalPrimes',
                'SUM(fp.totalDeductions) as totalDeductions',
                'SUM(fp.salaireNet) as totalNet',
                'COUNT(fp.id) as ficheCount'
            )
            ->join('fp.employee', 'e')
            ->where('e.rhId = :rhId')
            ->andWhere('fp.annee = :annee')
            ->setParameter('rhId', $rhId)
            ->setParameter('annee', $annee)
            ->groupBy('fp.mois')
            ->orderBy('fp.mois', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
