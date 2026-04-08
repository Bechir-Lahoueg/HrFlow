<?php

namespace App\Service;

use App\Entity\FichePaie;
use App\Entity\Employee;
use App\Repository\FichePaieRepository;
use App\Repository\PrimeRepository;
use App\Repository\DeductionRepository;
use App\Repository\EmployeeRepository;
use Doctrine\ORM\EntityManagerInterface;

final class FichePaieService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FichePaieRepository $fichePaieRepository,
        private readonly PrimeRepository $primeRepository,
        private readonly DeductionRepository $deductionRepository,
        private readonly EmployeeRepository $employeeRepository,
    ) {
    }

    /** @return FichePaie[] */
    public function getFichePaiesByRh(int $rhId): array
    {
        return $this->fichePaieRepository->findByRh($rhId);
    }

    /** @return FichePaie[] */
    public function searchFichePaies(int $rhId, string $employeeSearch = '', string $periodSearch = '', string $sort = 'createdAt-DESC'): array
    {
        return $this->fichePaieRepository->findByRhAndSearch($rhId, $employeeSearch, $periodSearch, $sort);
    }

    public function getFichePaieById(int $id): ?FichePaie
    {
        return $this->fichePaieRepository->find($id);
    }

    public function createFichePaie(int $employeeId, int $mois, int $annee, string $salaireBrut, ?string $notes = null): array
    {
        if ($mois < 1 || $mois > 12) {
            return ['success' => false, 'message' => 'Month must be between 1 and 12'];
        }

        if ($annee < 2000 || $annee > 2100) {
            return ['success' => false, 'message' => 'Year must be valid'];
        }

        $employee = $this->employeeRepository->find($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found'];
        }

        // Check for duplicates
        $existing = $this->fichePaieRepository->findByEmployeeAndPeriodSingle($employeeId, $mois, $annee);
        if ($existing !== null) {
            return ['success' => false, 'message' => 'Pay slip for this period already exists'];
        }

        $brut = (float) $salaireBrut;
        if ($brut < 0) {
            return ['success' => false, 'message' => 'Gross salary must be positive'];
        }

        // Calculate totals
        $totalPrimes = $this->primeRepository->getTotalByEmployeeAndPeriod($employeeId, $mois, $annee);
        $totalDeductions = $this->deductionRepository->getTotalByEmployeeAndPeriod($employeeId, $mois, $annee);

        $fichePaie = new FichePaie();
        $fichePaie->setEmployee($employee)
            ->setMois($mois)
            ->setAnnee($annee)
            ->setSalaireBrut((string) $salaireBrut)
            ->setTotalPrimes($totalPrimes)
            ->setTotalDeductions($totalDeductions)
            ->setNotes($notes);

        $fichePaie->calculateSalaireNet();

        $this->em->persist($fichePaie);
        $this->em->flush();

        return ['success' => true, 'message' => 'Pay slip created successfully', 'id' => $fichePaie->getId()];
    }

    public function updateFichePaie(int $id, int $mois, int $annee, string $salaireBrut, ?string $notes = null): array
    {
        $fichePaie = $this->fichePaieRepository->find($id);
        if (!$fichePaie) {
            return ['success' => false, 'message' => 'Pay slip not found'];
        }

        if ($mois < 1 || $mois > 12) {
            return ['success' => false, 'message' => 'Month must be between 1 and 12'];
        }

        if ($annee < 2000 || $annee > 2100) {
            return ['success' => false, 'message' => 'Year must be valid'];
        }

        $brut = (float) $salaireBrut;
        if ($brut < 0) {
            return ['success' => false, 'message' => 'Gross salary must be positive'];
        }

        // Recalculate totals
        $totalPrimes = $this->primeRepository->getTotalByEmployeeAndPeriod($fichePaie->getEmployee()->getId(), $mois, $annee);
        $totalDeductions = $this->deductionRepository->getTotalByEmployeeAndPeriod($fichePaie->getEmployee()->getId(), $mois, $annee);

        $fichePaie->setMois($mois)
            ->setAnnee($annee)
            ->setSalaireBrut($salaireBrut)
            ->setTotalPrimes($totalPrimes)
            ->setTotalDeductions($totalDeductions)
            ->setNotes($notes);

        $fichePaie->calculateSalaireNet();

        $this->em->flush();

        return ['success' => true, 'message' => 'Pay slip updated successfully'];
    }

    public function generateFichePaie(int $employeeId, int $mois, int $annee, string $salaireBrut): array
    {
        // Check if fiche paie already exists
        $existing = $this->fichePaieRepository->findByEmployeeAndPeriodSingle($employeeId, $mois, $annee);
        if ($existing !== null) {
            return ['success' => false, 'message' => 'Pay slip already exists for this period'];
        }

        return $this->createFichePaie($employeeId, $mois, $annee, $salaireBrut);
    }

    public function deleteFichePaie(int $id): array
    {
        $fichePaie = $this->fichePaieRepository->find($id);
        if (!$fichePaie) {
            return ['success' => false, 'message' => 'Pay slip not found'];
        }

        $this->em->remove($fichePaie);
        $this->em->flush();

        return ['success' => true, 'message' => 'Pay slip deleted successfully'];
    }

    public function getStatsByRh(int $rhId): array
    {
        return $this->fichePaieRepository->getStatsByRh($rhId);
    }

    public function getFichePaiesByEmployee(int $employeeId): array
    {
        return $this->fichePaieRepository->findBy(['employee' => $employeeId], ['annee' => 'DESC', 'mois' => 'DESC']);
    }

    public function refreshFichePaieTotals(int $fichePaieId): array
    {
        $fichePaie = $this->fichePaieRepository->find($fichePaieId);
        if (!$fichePaie) {
            return ['success' => false, 'message' => 'Pay slip not found'];
        }

        $employee = $fichePaie->getEmployee();
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found'];
        }

        // Recalculate from primes and deductions
        $totalPrimes = $this->primeRepository->getTotalByEmployeeAndPeriod(
            $employee->getId(),
            $fichePaie->getMois(),
            $fichePaie->getAnnee()
        );
        $totalDeductions = $this->deductionRepository->getTotalByEmployeeAndPeriod(
            $employee->getId(),
            $fichePaie->getMois(),
            $fichePaie->getAnnee()
        );

        $fichePaie->setTotalPrimes($totalPrimes)
            ->setTotalDeductions($totalDeductions);

        $fichePaie->calculateSalaireNet();

        $this->em->flush();

        return ['success' => true, 'message' => 'Pay slip totals refreshed'];
    }
}
