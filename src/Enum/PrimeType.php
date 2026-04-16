<?php

namespace App\Enum;

/**
 * PrimeType Enum - All valid bonus/prime types
 * 
 * @package App\Enum
 */
enum PrimeType: string
{
    case BONUS = 'Bonus';
    case PRIME = 'Prime';
    case PRIME_EXCEPTIONNELLE = 'Prime Exceptionnelle';
    case INDEMNITE = 'Indemnité';
    case ALLOCATION_FAMILIALE = 'Allocation Familiale';
    case PRIME_RENDEMENT = 'Prime de Rendement';
    case PRIME_PERFORMANCE = 'Prime de performance';
    case PRIME_ANCIENNETE = 'Prime d\'Ancienneté';
    case GRATIFICATION = 'Gratification';
    case AUTRE = 'Autre Prime';

    /**
     * Get human-readable label for the enum value
     */
    public function label(): string
    {
        return $this->value;
    }

    /**
     * Get all enum cases as key => value array
     * Useful for dropdowns in forms
     * Returns: enum_value (case name) => label (display label)
     * 
     * @return array<string, string>
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->value] = $case->value;  // value as both key and label for display
        }
        return $choices;
    }

    /**
     * Check if a value is valid
     */
    public static function isValid(string $value): bool
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get enum case from string value
     */
    public static function fromValue(string $value): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }
        return null;
    }
}
