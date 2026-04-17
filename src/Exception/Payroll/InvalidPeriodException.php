<?php

namespace App\Exception\Payroll;

/**
 * Exception thrown when invalid period is provided
 */
class InvalidPeriodException extends \DomainException
{
    public static function invalidMonth(int $mois): self
    {
        return new self(
            sprintf('Month must be between 1 and 12, got: %d', $mois)
        );
    }

    public static function invalidYear(int $annee): self
    {
        return new self(
            sprintf('Year must be between 2000 and 2100, got: %d', $annee)
        );
    }
}
