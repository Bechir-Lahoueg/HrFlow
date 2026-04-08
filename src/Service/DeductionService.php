<?php

namespace App\Service;

use App\Entity\Deduction;
use App\Entity\Employee;
use App\Repository\DeductionRepository;
use App\Repository\EmployeeRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DeductionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DeductionRepository $deductionRepository,
        private readonly EmployeeRepository $employeeRepository,
    ) {
    }

    /** @return Deduction[] */
    public function getDeductionsByRh(int $rhId): array
    {
        return $this->deductionRepository->findByRh($rhId);
    }

    /** @return Deduction[] */
    public function searchDeductions(int $rhId, string $employeeSearch = '', string $typeSearch = '', string $sort = 'dateDeduction-DESC'): array
    {
        return $this->deductionRepository->findByRhAndSearch($rhId, $employeeSearch, $typeSearch, $sort);
    }

    public function getDeductionById(int $id): ?Deduction
    {
        return $this->deductionRepository->find($id);
    }

    public function createDeduction(int $employeeId, string $typeDeduction, string $montant, string $dateDeduction, ?string $motif = null): array
    {
        $employee = $this->employeeRepository->find($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found'];
        }

        try {
            $date = new \DateTime($dateDeduction);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Invalid date format'];
        }

        $amount = (float) $montant;
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Amount must be positive'];
        }

        if (trim($typeDeduction) === '') {
            return ['success' => false, 'message' => 'Deduction type is required'];
        }

        $deduction = new Deduction();
        $deduction->setEmployee($employee)
            ->setTypeDeduction(trim($typeDeduction))
            ->setMontant((string) $montant)
            ->setDateDeduction($date)
            ->setMotif($motif);

        $this->em->persist($deduction);
        $this->em->flush();

        return ['success' => true, 'message' => 'Deduction created successfully', 'id' => $deduction->getId()];
    }

    public function updateDeduction(int $id, string $typeDeduction, string $montant, string $dateDeduction, ?string $motif = null): array
    {
        $deduction = $this->deductionRepository->find($id);
        if (!$deduction) {
            return ['success' => false, 'message' => 'Deduction not found'];
        }

        try {
            $date = new \DateTime($dateDeduction);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Invalid date format'];
        }

        $amount = (float) $montant;
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Amount must be positive'];
        }

        if (trim($typeDeduction) === '') {
            return ['success' => false, 'message' => 'Deduction type is required'];
        }

        $deduction->setTypeDeduction(trim($typeDeduction))
            ->setMontant((string) $montant)
            ->setDateDeduction($date)
            ->setMotif($motif);

        $this->em->flush();

        return ['success' => true, 'message' => 'Deduction updated successfully'];
    }

    public function deleteDeduction(int $id): array
    {
        $deduction = $this->deductionRepository->find($id);
        if (!$deduction) {
            return ['success' => false, 'message' => 'Deduction not found'];
        }

        $this->em->remove($deduction);
        $this->em->flush();

        return ['success' => true, 'message' => 'Deduction deleted successfully'];
    }

    public function getStatsByRh(int $rhId): array
    {
        return $this->deductionRepository->getStatsByRh($rhId);
    }

    public function getDeductionsByEmployee(int $employeeId): array
    {
        return $this->deductionRepository->findByEmployee($employeeId);
    }

    public function getDeductionsByEmployeeAndPeriod(int $employeeId, int $mois, int $annee): array
    {
        return $this->deductionRepository->findByEmployeeAndPeriod($employeeId, $mois, $annee);
    }
}
