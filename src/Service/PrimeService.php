<?php

namespace App\Service;

use App\Entity\Paie\Prime;
use App\Entity\Rh\Employee;
use App\Repository\Paie\PrimeRepository;
use App\Repository\Rh\EmployeeRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PrimeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PrimeRepository $primeRepository,
        private readonly EmployeeRepository $employeeRepository,
    ) {
    }

    /** @return Prime[] */
    public function getPrimesByRh(int $rhId): array
    {
        return $this->primeRepository->findByRh($rhId);
    }

    /** @return Prime[] */
    public function searchPrimes(int $rhId, string $employeeSearch = '', string $typeSearch = '', string $sort = 'dateAttribution-DESC'): array
    {
        return $this->primeRepository->findByRhAndSearch($rhId, $employeeSearch, $typeSearch, $sort);
    }

    public function getPrimeById(int $id): ?Prime
    {
        return $this->primeRepository->find($id);
    }

    public function createPrime(int $employeeId, string $typePrime, string $montant, string $dateAttribution, ?string $motif = null): array
    {
        $employee = $this->employeeRepository->find($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found'];
        }

        try {
            $date = new \DateTime($dateAttribution);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Invalid date format'];
        }

        $amount = (float) $montant;
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Amount must be positive'];
        }

        if (trim($typePrime) === '') {
            return ['success' => false, 'message' => 'Prime type is required'];
        }

        $prime = new Prime();
        $prime->setEmployee($employee)
            ->setTypePrime(trim($typePrime))
            ->setMontant((string) $montant)
            ->setDateAttribution($date)
            ->setMotif($motif);

        $this->em->persist($prime);
        $this->em->flush();

        return ['success' => true, 'message' => 'Prime created successfully', 'id' => $prime->getId()];
    }

    public function updatePrime(int $id, string $typePrime, string $montant, string $dateAttribution, ?string $motif = null): array
    {
        $prime = $this->primeRepository->find($id);
        if (!$prime) {
            return ['success' => false, 'message' => 'Prime not found'];
        }

        try {
            $date = new \DateTime($dateAttribution);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Invalid date format'];
        }

        $amount = (float) $montant;
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Amount must be positive'];
        }

        if (trim($typePrime) === '') {
            return ['success' => false, 'message' => 'Prime type is required'];
        }

        $prime->setTypePrime(trim($typePrime))
            ->setMontant((string) $montant)
            ->setDateAttribution($date)
            ->setMotif($motif);

        $this->em->flush();

        return ['success' => true, 'message' => 'Prime updated successfully'];
    }

    public function deletePrime(int $id): array
    {
        $prime = $this->primeRepository->find($id);
        if (!$prime) {
            return ['success' => false, 'message' => 'Prime not found'];
        }

        $this->em->remove($prime);
        $this->em->flush();

        return ['success' => true, 'message' => 'Prime deleted successfully'];
    }

    public function getStatsByRh(int $rhId): array
    {
        return $this->primeRepository->getStatsByRh($rhId);
    }

    public function getPrimesByEmployee(int $employeeId): array
    {
        return $this->primeRepository->findByEmployee($employeeId);
    }

    public function getPrimesByEmployeeAndPeriod(int $employeeId, int $mois, int $annee): array
    {
        return $this->primeRepository->findByEmployeeAndPeriod($employeeId, $mois, $annee);
    }
}
