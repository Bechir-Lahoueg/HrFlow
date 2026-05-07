<?php

namespace App\Service\Paie;

use App\Enum\PrimeType;
use App\Enum\DeductionType;
use App\Exception\Payroll\InvalidCompensationAmountException;

/**
 * CompensationValidationService - Validates prime and deduction amounts against allowed ranges
 * 
 * @package App\Service
 */
final class CompensationValidationService
{
    /**
     * Prime type allowed ranges (min - max)
     * Format: 'TypeName' => ['min' => min_value, 'max' => max_value]
     */
    private const PRIME_RANGES = [
        'Bonus' => ['min' => 100, 'max' => 300],
        'Prime' => ['min' => 150, 'max' => 400],
        'Prime Exceptionnelle' => ['min' => 200, 'max' => 1000],
        'Indemnité' => ['min' => 50, 'max' => 200],
        'Allocation Familiale' => ['min' => 100, 'max' => 300],
        'Prime de Rendement' => ['min' => 100, 'max' => 500],
        'Prime d\'Ancienneté' => ['min' => 50, 'max' => 300],
        'Gratification' => ['min' => 100, 'max' => 600],
        'Autre Prime' => ['min' => 0, 'max' => 10000],  // No restriction
    ];

    /**
     * Deduction type allowed ranges (min - max)
     * Format: 'TypeName' => ['min' => min_value, 'max' => max_value]
     * Note: Percentage-based deductions (IRPP, CNSS) are set to 0 (user must enter)
     * Variable deductions (Avance sur Salaire) have no max limit
     */
    private const DEDUCTION_RANGES = [
        'Impôt sur le Revenu (IRPP)' => ['min' => 0, 'max' => 0],  // 0 means no validation (percentage-based)
        'Cotisation CNSS' => ['min' => 0, 'max' => 0],              // 0 means no validation (percentage-based)
        'Assurance Mutuelle' => ['min' => 20, 'max' => 80],
        'Charge Syndicale' => ['min' => 10, 'max' => 30],
        'Avance sur Salaire' => ['min' => 0, 'max' => 0],           // 0 means no validation (variable)
        'Crédit/Remboursement' => ['min' => 100, 'max' => 500],
        'Retenue Disciplinaire' => ['min' => 20, 'max' => 200],
        'Impôt Local' => ['min' => 10, 'max' => 50],
        'Assurance Maladie' => ['min' => 30, 'max' => 100],
        'Autre Déduction' => ['min' => 0, 'max' => 10000],  // No restriction
    ];

    /**
     * Validate a prime amount
     * 
     * @param string $primeTypeName The prime type name (from enum value)
     * @param float $montant The amount to validate
     * @throws InvalidCompensationAmountException if validation fails
     */
    public function validatePrimeAmount(string $primeTypeName, float $montant): void
    {
        if (!isset(self::PRIME_RANGES[$primeTypeName])) {
            return;  // Unknown type, skip validation
        }

        $range = self::PRIME_RANGES[$primeTypeName];
        
        if ($montant < $range['min'] || $montant > $range['max']) {
            throw InvalidCompensationAmountException::forPrime(
                $primeTypeName,
                $montant,
                $range['min'],
                $range['max']
            );
        }
    }

    /**
     * Validate a deduction amount
     * 
     * @param string $deductionTypeName The deduction type name (from enum value)
     * @param float $montant The amount to validate
     * @throws InvalidCompensationAmountException if validation fails
     */
    public function validateDeductionAmount(string $deductionTypeName, float $montant): void
    {
        if (!isset(self::DEDUCTION_RANGES[$deductionTypeName])) {
            return;  // Unknown type, skip validation
        }

        $range = self::DEDUCTION_RANGES[$deductionTypeName];
        
        // Skip validation for percentage-based or variable deductions (0 max means no validation)
        if ($range['max'] === 0) {
            return;
        }

        if ($montant < $range['min'] || $montant > $range['max']) {
            throw InvalidCompensationAmountException::forDeduction(
                $deductionTypeName,
                $montant,
                $range['min'],
                $range['max']
            );
        }
    }

    /**
     * Get min/max range for a prime type
     * 
     * @param string $primeTypeName The prime type name
     * @return array<mixed>|null Array with 'min' and 'max' keys, or null if not found
     */
    public function getPrimeRange(string $primeTypeName): ?array
    {
        return self::PRIME_RANGES[$primeTypeName] ?? null;
    }

    /**
     * Get min/max range for a deduction type
     * 
     * @param string $deductionTypeName The deduction type name
     * @return array<mixed>|null Array with 'min' and 'max' keys, or null if not found
     */
    public function getDeductionRange(string $deductionTypeName): ?array
    {
        return self::DEDUCTION_RANGES[$deductionTypeName] ?? null;
    }

    /**
     * Get all prime ranges
     * 
     * @return array<mixed>
     */
    public function getAllPrimeRanges(): array
    {
        return self::PRIME_RANGES;
    }

    /**
     * Get all deduction ranges
     * 
     * @return array<mixed>
     */
    public function getAllDeductionRanges(): array
    {
        return self::DEDUCTION_RANGES;
    }
}
