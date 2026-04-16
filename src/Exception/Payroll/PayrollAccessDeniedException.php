<?php

namespace App\Exception\Payroll;

/**
 * Exception thrown when access to payroll data is denied
 */
class PayrollAccessDeniedException extends \DomainException
{
    public static function forEmployee(int $employeeId, int $rhId): self
    {
        return new self(
            sprintf('RH with ID %d does not have access to employee %d payroll data', $rhId, $employeeId)
        );
    }
}
