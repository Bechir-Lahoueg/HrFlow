<?php

namespace App\Service\Paie;

use App\DTO\Payroll\PrimeRequestDTO;
use App\DTO\Payroll\PrimeResponseDTO;
use App\Entity\Paie\Prime;
use App\Enum\PrimeType;
use App\Exception\Payroll\EmployeeNotFoundException;
use App\Repository\Paie\PrimeRepository;
use App\Repository\Rh\EmployeeRepository;
use App\Service\Shared\CachingService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * PrimeService - Business logic for bonus/prime management
 */
final class PrimeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PrimeRepository $primeRepository,
        private readonly EmployeeRepository $employeeRepository,
        private readonly CachingService $cachingService,
        private readonly FichePaieService $fichePaieService,
        private readonly CompensationValidationService $validationService,
    ) {
    }

    /**
     * Get all primes for an RH
     *
     * @return PrimeResponseDTO[]
     */
    public function getPrimesByRh(int $rhId): array
    {
        $primes = $this->primeRepository->findByRh($rhId);
        return array_map(fn(Prime $p) => new PrimeResponseDTO($p), $primes);
    }

    /**
     * Search primes with filters
     *
     * @return PrimeResponseDTO[]
     */
    public function searchPrimes(
        int $rhId,
        string $employeeSearch = '',
        string $typeSearch = '',
        string $sort = 'dateAttribution-DESC'
    ): array {
        $primes = $this->primeRepository->findByRhAndSearch($rhId, $employeeSearch, $typeSearch, $sort);
        return array_map(fn(Prime $p) => new PrimeResponseDTO($p), $primes);
    }

    /**
     * Get prime by ID
     */
    public function getPrimeById(int $id): PrimeResponseDTO
    {
        $prime = $this->primeRepository->find($id);
        if (!$prime) {
            throw new \DomainException(sprintf('Prime with ID %d not found', $id));
        }
        return new PrimeResponseDTO($prime);
    }

    /**
     * Create a new prime
     */
    public function createPrime(PrimeRequestDTO $dto): PrimeResponseDTO
    {
        $employee = $this->employeeRepository->find($dto->employeeId);
        if (!$employee) {
            throw EmployeeNotFoundException::withId($dto->employeeId);
        }

        if ($dto->typePrime === null) {
            throw new \DomainException('Prime type is required');
        }

        $amount = (float) $dto->montant;
        if ($amount <= 0) {
            throw new \DomainException('Amount must be positive');
        }

        // Validate amount against allowed range
        $this->validationService->validatePrimeAmount($dto->typePrime->value, $amount);

        if ($dto->dateAttribution === null) {
            throw new \DomainException('Attribution date is required');
        }

        // Block if the target period is already locked (paid)
        $targetMois = (int) $dto->dateAttribution->format('m');
        $targetAnnee = (int) $dto->dateAttribution->format('Y');
        if ($this->fichePaieService->isPeriodLocked($dto->employeeId, $targetMois, $targetAnnee)) {
            throw new \DomainException('Impossible d\'ajouter une prime : la fiche de paie de cette période est déjà payée.');
        }

        $prime = new Prime();
        $prime->setEmployee($employee)
            ->setTypePrime($dto->typePrime)
            ->setMontant($dto->montant)
            ->setDateAttribution($dto->dateAttribution)
            ->setMotif($dto->motif);

        $this->em->persist($prime);
        $this->em->flush();

        // Invalidate cache
        $this->cachingService->forget(CachingService::employeePrimesKey($dto->employeeId));

        // Recalculate fiche de paie totals
        $this->fichePaieService->recalculateTotals(
            $dto->employeeId,
            (int) $dto->dateAttribution->format('m'),
            (int) $dto->dateAttribution->format('Y')
        );

        return new PrimeResponseDTO($prime);
    }

    /**
     * Update an existing prime
     */
    public function updatePrime(int $id, PrimeRequestDTO $dto): PrimeResponseDTO
    {
        $prime = $this->primeRepository->find($id);
        if (!$prime) {
            throw new \DomainException(sprintf('Prime with ID %d not found', $id));
        }

        if ($dto->typePrime === null) {
            throw new \DomainException('Prime type is required');
        }

        $amount = (float) $dto->montant;
        if ($amount <= 0) {
            throw new \DomainException('Amount must be positive');
        }
        // Validate amount against allowed range
        $this->validationService->validatePrimeAmount($dto->typePrime->value, $amount);
        if ($dto->dateAttribution === null) {
            throw new \DomainException('Attribution date is required');
        }

        // Save old period before update (in case date changed)
        $oldMois = $prime->getMonth();
        $oldAnnee = $prime->getYear();
        $employeeId = $prime->getEmployee()->getId();

        $prime->setTypePrime($dto->typePrime)
            ->setMontant($dto->montant)
            ->setDateAttribution($dto->dateAttribution)
            ->setMotif($dto->motif);

        $this->em->flush();

        // Invalidate cache
        $this->cachingService->forget(CachingService::employeePrimesKey($employeeId));

        // Recalculate fiche de paie totals for the new period
        $newMois = (int) $dto->dateAttribution->format('m');
        $newAnnee = (int) $dto->dateAttribution->format('Y');
        $this->fichePaieService->recalculateTotals($employeeId, $newMois, $newAnnee);

        // If the period changed, also recalculate the old period
        if ($oldMois !== $newMois || $oldAnnee !== $newAnnee) {
            $this->fichePaieService->recalculateTotals($employeeId, $oldMois, $oldAnnee);
        }

        return new PrimeResponseDTO($prime);
    }

    /**
     * Delete a prime
     */
    public function deletePrime(int $id): void
    {
        $prime = $this->primeRepository->find($id);
        if (!$prime) {
            throw new \DomainException(sprintf('Prime with ID %d not found', $id));
        }

        $employeeId = $prime->getEmployee()->getId();
        $mois = $prime->getMonth();
        $annee = $prime->getYear();

        $this->em->remove($prime);
        $this->em->flush();

        // Invalidate cache
        $this->cachingService->forget(CachingService::employeePrimesKey($employeeId));

        // Recalculate fiche de paie totals
        $this->fichePaieService->recalculateTotals($employeeId, $mois, $annee);
    }

    /**
     * Get all primes for an employee
     *
     * @return PrimeResponseDTO[]
     */
    public function getPrimesByEmployee(int $employeeId): array
    {
        $primes = $this->primeRepository->findByEmployee($employeeId);
        return array_map(fn(Prime $p) => new PrimeResponseDTO($p), $primes);
    }

    /**
     * Get primes for an employee during a specific period
     *
     * @return PrimeResponseDTO[]
     */
    public function getPrimesByEmployeeAndPeriod(int $employeeId, int $mois, int $annee): array
    {
        $primes = $this->primeRepository->findByEmployeeAndPeriod($employeeId, $mois, $annee);
        return array_map(fn(Prime $p) => new PrimeResponseDTO($p), $primes);
    }

    /**
     * Get prime type choices for forms
     *
     * @return array<string, string>
     */
    public static function getPrimeTypeChoices(): array
    {
        return PrimeType::choices();
    }
}
