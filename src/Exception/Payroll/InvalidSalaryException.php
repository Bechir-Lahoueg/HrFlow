<?php

namespace App\Exception\Payroll;

/**
 * Exception thrown when an invalid salary amount is provided
 */
class InvalidSalaryException extends \DomainException
{
    public static function negativeAmount(string $amount): self
    {
        return new self(
            sprintf('Salary amount cannot be negative: %s', $amount)
        );
    }

    public static function invalidFormat(string $amount): self
    {
        return new self(
            sprintf('Salary amount has invalid format: %s', $amount)
        );
    }
}
