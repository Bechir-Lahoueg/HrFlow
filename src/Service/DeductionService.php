<?php

namespace App\Service;

use App\DTO\Payroll\DeductionRequestDTO;
use App\DTO\Payroll\DeductionResponseDTO;
use App\Entity\Paie\Deduction;
use App\Enum\DeductionType;
use App\Exception\Payroll\EmployeeNotFoundException;
use App\Repository\Paie\DeductionRepository;
use App\Repository\Rh\EmployeeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * DeductionService - Business logic for deduction management
 */
final class DeductionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DeductionRepository $deductionRepository,
        private readonly EmployeeRepository $employeeRepository,
        private readonly CachingService $cachingService,
        private readonly FichePaieService $fichePaieService,
        private readonly CompensationValidationService $validationService,
    ) {
    }

    /**
     * Get all deductions for an RH
     *
     * @return DeductionResponseDTO[]
     */
    public function getDeductionsByRh(int $rhId): array
    {
        $deductions = $this->deductionRepository->findByRh($rhId);
        return array_map(fn(Deduction $d) => new DeductionResponseDTO($d), $deductions);
    }

    /**
     * Search deductions with filters
     *
     * @return DeductionResponseDTO[]
     */
    public function searchDeductions(
        int $rhId,
        string $employeeSearch = '',
        string $typeSearch = '',
        string $sort = 'dateDeduction-DESC'
    ): array {
        $deductions = $this->deductionRepository->findByRhAndSearch($rhId, $employeeSearch, $typeSearch, $sort);
        return array_map(fn(Deduction $d) => new DeductionResponseDTO($d), $deductions);
    }

    /**
     * Get deduction by ID
     */
    public function getDeductionById(int $id): DeductionResponseDTO
    {
        $deduction = $this->deductionRepository->find($id);
        if (!$deduction) {
            throw new \DomainException(sprintf('Deduction with ID %d not found', $id));
        }
        return new DeductionResponseDTO($deduction);
    }

    /**
     * Create a new deduction
     */
    public function createDeduction(DeductionRequestDTO $dto): DeductionResponseDTO
    {
        $employee = $this->employeeRepository->find($dto->employeeId);
        if (!$employee) {
            throw EmployeeNotFoundException::withId($dto->employeeId);
        }

        if ($dto->typeDeduction === null) {
            throw new \DomainException('Deduction type is required');
        }

        $amount = (float) $dto->montant;
        if ($amount <= 0) {
            throw new \DomainException('Amount must be positive');
        }

        // Validate amount against allowed range
        $this->validationService->validateDeductionAmount($dto->typeDeduction->value, $amount);

        if ($dto->dateDeduction === null) {
            throw new \DomainException('Deduction date is required');
        }

        $deduction = new Deduction();
        $deduction->setEmployee($employee)
            ->setTypeDeduction($dto->typeDeduction)
            ->setMontant($dto->montant)
            ->setDateDeduction($dto->dateDeduction)
            ->setMotif($dto->motif);

        $this->em->persist($deduction);
        $this->em->flush();

        // Invalidate cache
        $this->cachingService->forget(CachingService::employeeDeductionsKey($dto->employeeId));

        // Recalculate fiche de paie totals
        $this->fichePaieService->recalculateTotals(
            $dto->employeeId,
            (int) $dto->dateDeduction->format('m'),
            (int) $dto->dateDeduction->format('Y')
        );

        return new DeductionResponseDTO($deduction);
    }

    /**
     * Update an existing deduction
     */
    public function updateDeduction(int $id, DeductionRequestDTO $dto): DeductionResponseDTO
    {
        $deduction = $this->deductionRepository->find($id);
        if (!$deduction) {
            throw new \DomainException(sprintf('Deduction with ID %d not found', $id));
        }

        if ($dto->typeDeduction === null) {
            throw new \DomainException('Deduction type is required');
        }

        $amount = (float) $dto->montant;
        if ($amount <= 0) {
            throw new \DomainException('Amount must be positive');
        }

        // Validate amount against allowed range
        $this->validationService->validateDeductionAmount($dto->typeDeduction->value, $amount);

        if ($dto->dateDeduction === null) {
            throw new \DomainException('Deduction date is required');
        }

        // Save old period before update (in case date changed)
        $oldMois = $deduction->getMonth();
        $oldAnnee = $deduction->getYear();
        $employeeId = $deduction->getEmployee()->getId();

        $deduction->setTypeDeduction($dto->typeDeduction)
            ->setMontant($dto->montant)
            ->setDateDeduction($dto->dateDeduction)
            ->setMotif($dto->motif);

        $this->em->flush();

        // Invalidate cache
        $this->cachingService->forget(CachingService::employeeDeductionsKey($employeeId));

        // Recalculate fiche de paie totals for the new period
        $newMois = (int) $dto->dateDeduction->format('m');
        $newAnnee = (int) $dto->dateDeduction->format('Y');
        $this->fichePaieService->recalculateTotals($employeeId, $newMois, $newAnnee);

        // If the period changed, also recalculate the old period
        if ($oldMois !== $newMois || $oldAnnee !== $newAnnee) {
            $this->fichePaieService->recalculateTotals($employeeId, $oldMois, $oldAnnee);
        }

        return new DeductionResponseDTO($deduction);
    }

    /**
     * Delete a deduction
     */
    public function deleteDeduction(int $id): void
    {
        $deduction = $this->deductionRepository->find($id);
        if (!$deduction) {
            throw new \DomainException(sprintf('Deduction with ID %d not found', $id));
        }

        $employeeId = $deduction->getEmployee()->getId();
        $mois = $deduction->getMonth();
        $annee = $deduction->getYear();

        $this->em->remove($deduction);
        $this->em->flush();

        // Invalidate cache
        $this->cachingService->forget(CachingService::employeeDeductionsKey($employeeId));

        // Recalculate fiche de paie totals
        $this->fichePaieService->recalculateTotals($employeeId, $mois, $annee);
    }

    /**
     * Get all deductions for an employee
     *
     * @return DeductionResponseDTO[]
     */
    public function getDeductionsByEmployee(int $employeeId): array
    {
        $deductions = $this->deductionRepository->findByEmployee($employeeId);
        return array_map(fn(Deduction $d) => new DeductionResponseDTO($d), $deductions);
    }

    /**
     * Get deductions for an employee during a specific period
     *
     * @return DeductionResponseDTO[]
     */
    public function getDeductionsByEmployeeAndPeriod(int $employeeId, int $mois, int $annee): array
    {
        $deductions = $this->deductionRepository->findByEmployeeAndPeriod($employeeId, $mois, $annee);
        return array_map(fn(Deduction $d) => new DeductionResponseDTO($d), $deductions);
    }

    /**
     * Get deduction type choices for forms
     *
     * @return array<string, string>
     */
    public static function getDeductionTypeChoices(): array
    {
        return DeductionType::choices();
    }
}
