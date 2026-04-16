<?php

namespace App\Exception\Payroll;

/**
 * Exception thrown when a duplicate pay slip is detected
 */
class DuplicateFichePaieException extends \DomainException
{
    public static function forEmployeeAndPeriod(int $employeeId, int $mois, int $annee): self
    {
        return new self(
            sprintf(
                'A pay slip already exists for employee ID %d for %d/%d',
                $employeeId,
                $mois,
                $annee
            )
        );
    }
}
