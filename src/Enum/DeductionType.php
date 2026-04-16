<?php

namespace App\Enum;

/**
 * DeductionType Enum - All valid deduction types
 * 
 * @package App\Enum
 */
enum DeductionType: string
{
    case IRPP = 'Impôt sur le Revenu (IRPP)';
    case CNSS = 'Cotisation CNSS';
    case MUTUELLE = 'Assurance Mutuelle';
    case CHARGE_SYNDICALE = 'Charge Syndicale';
    case AVANCE_SALAIRE = 'Avance sur Salaire';
    case CREDIT_REMBOURSEMENT = 'Crédit/Remboursement';
    case RETENUE_DISCIPLINAIRE = 'Retenue Disciplinaire';
    case IMPOT_LOCAL = 'Impôt Local';
    case ASSURANCE_MALADIE = 'Assurance Maladie';
    case AUTRE = 'Autre Déduction';

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
