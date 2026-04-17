<?php

namespace App\Exception\Payroll;

/**
 * Exception thrown when a pay slip is not found
 */
class FichePaieNotFoundException extends \DomainException
{
    public static function withId(int $id): self
    {
        return new self(
            sprintf('Pay slip with ID %d not found', $id)
        );
    }
}
