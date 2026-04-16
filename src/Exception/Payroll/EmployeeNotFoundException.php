<?php

namespace App\Exception\Payroll;

/**
 * Exception thrown when an employee is not found
 */
class EmployeeNotFoundException extends \DomainException
{
    public static function withId(int $employeeId): self
    {
        return new self(
            sprintf('Employee with ID %d not found', $employeeId)
        );
    }
}
