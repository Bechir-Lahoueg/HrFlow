<?php

namespace App\Exception\Payroll;

/**
 * InvalidCompensationAmountException - Thrown when prime or deduction amount is outside allowed range
 */
final class InvalidCompensationAmountException extends \DomainException
{
    private string $type;
    private float $amount;
    private float $minAmount;
    private float $maxAmount;
    private string $category;  // 'prime' or 'deduction'

    private function __construct(
        string $type,
        float $amount,
        float $minAmount,
        float $maxAmount,
        string $category,
        string $message
    ) {
        parent::__construct($message);
        $this->type = $type;
        $this->amount = $amount;
        $this->minAmount = $minAmount;
        $this->maxAmount = $maxAmount;
        $this->category = $category;
    }

    public static function forPrime(
        string $primeType,
        float $amount,
        float $minAmount,
        float $maxAmount
    ): self {
        $message = sprintf(
            '%s doit être entre %s DT et %s DT (vous avez entré %s DT).',
            $primeType,
            number_format($minAmount, 2, '.', ''),
            number_format($maxAmount, 2, '.', ''),
            number_format($amount, 2, '.', '')
        );

        return new self($primeType, $amount, $minAmount, $maxAmount, 'prime', $message);
    }

    public static function forDeduction(
        string $deductionType,
        float $amount,
        float $minAmount,
        float $maxAmount
    ): self {
        $message = sprintf(
            '%s doit être entre %s DT et %s DT (vous avez entré %s DT).',
            $deductionType,
            number_format($minAmount, 2, '.', ''),
            number_format($maxAmount, 2, '.', ''),
            number_format($amount, 2, '.', '')
        );

        return new self($deductionType, $amount, $minAmount, $maxAmount, 'deduction', $message);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getMinAmount(): float
    {
        return $this->minAmount;
    }

    public function getMaxAmount(): float
    {
        return $this->maxAmount;
    }

    public function getCategory(): string
    {
        return $this->category;
    }
}
