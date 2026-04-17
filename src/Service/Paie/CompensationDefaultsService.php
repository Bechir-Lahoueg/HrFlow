<?php

namespace App\Service\Paie;

use App\Enum\PrimeType;
use App\Enum\DeductionType;

/**
 * CompensationDefaultsService - Provides default montants for primes and deductions
 * 
 * @package App\Service
 */
final class CompensationDefaultsService
{
    /**
     * Prime type default amounts (minimum value from range)
     * Format: 'TypeName' => default_amount
     */
    private const PRIME_DEFAULTS = [
        'Bonus' => 100,
        'Prime' => 150,
        'Prime Exceptionnelle' => 200,
        'Indemnité' => 50,
        'Allocation Familiale' => 100,
        'Prime de Rendement' => 100,
        'Prime d\'Ancienneté' => 50,
        'Gratification' => 100,
        'Autre Prime' => 0,
    ];

    /**
     * Deduction type default amounts (minimum value from range)
     * Format: 'TypeName' => default_amount
     * Note: Percentage-based deductions are set to 0 (user must enter)
     */
    private const DEDUCTION_DEFAULTS = [
        'Impôt sur le Revenu (IRPP)' => 0,  // 10-25% of salary (user calculates)
        'Cotisation CNSS' => 0,             // ~9.18% of salary (user calculates)
        'Assurance Mutuelle' => 20,
        'Charge Syndicale' => 10,
        'Avance sur Salaire' => 0,          // Variable (user enters)
        'Crédit/Remboursement' => 100,
        'Retenue Disciplinaire' => 20,
        'Impôt Local' => 10,
        'Assurance Maladie' => 30,
        'Autre Déduction' => 0,
    ];

    /**
     * Get default montant for a prime type
     * 
     * @param string $primeTypeName The prime type name (from enum value)
     * @return float The default montant, or 0 if not found
     */
    public function getPrimeDefault(string $primeTypeName): float
    {
        return (float) (self::PRIME_DEFAULTS[$primeTypeName] ?? 0);
    }

    /**
     * Get default montant for a deduction type
     * 
     * @param string $deductionTypeName The deduction type name (from enum value)
     * @return float The default montant, or 0 if not found
     */
    public function getDeductionDefault(string $deductionTypeName): float
    {
        return (float) (self::DEDUCTION_DEFAULTS[$deductionTypeName] ?? 0);
    }

    /**
     * Get all prime defaults as array
     * 
     * @return array<string, float>
     */
    public function getAllPrimeDefaults(): array
    {
        $result = [];
        foreach (self::PRIME_DEFAULTS as $type => $amount) {
            $result[$type] = (float) $amount;
        }
        return $result;
    }

    /**
     * Get all deduction defaults as array
     * 
     * @return array<string, float>
     */
    public function getAllDeductionDefaults(): array
    {
        $result = [];
        foreach (self::DEDUCTION_DEFAULTS as $type => $amount) {
            $result[$type] = (float) $amount;
        }
        return $result;
    }

    /**
     * Get default montant by type (prime or deduction)
     * 
     * @param string $typeName The type name
     * @param string $typeCategory Either 'prime' or 'deduction'
     * @return float The default montant
     */
    public function getDefaultByType(string $typeName, string $typeCategory = 'prime'): float
    {
        if ($typeCategory === 'deduction') {
            return $this->getDeductionDefault($typeName);
        }
        return $this->getPrimeDefault($typeName);
    }
}
