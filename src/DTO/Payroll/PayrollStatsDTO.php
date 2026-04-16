<?php

namespace App\DTO\Payroll;

/**
 * Payroll Stats DTO - Statistics for payroll dashboard
 */
class PayrollStatsDTO
{
    public string $totalSalaireBrut = '0.00';
    public string $totalPrimes = '0.00';
    public string $totalDeductions = '0.00';
    public string $totalSalaireNet = '0.00';
    public int $fichesPaieCount = 0;
    public int $employeesCount = 0;

    public function __construct(
        string $totalSalaireBrut = '0.00',
        string $totalPrimes = '0.00',
        string $totalDeductions = '0.00',
        string $totalSalaireNet = '0.00',
        int $fichesPaieCount = 0,
        int $employeesCount = 0,
    ) {
        $this->totalSalaireBrut = $totalSalaireBrut;
        $this->totalPrimes = $totalPrimes;
        $this->totalDeductions = $totalDeductions;
        $this->totalSalaireNet = $totalSalaireNet;
        $this->fichesPaieCount = $fichesPaieCount;
        $this->employeesCount = $employeesCount;
    }

    /**
     * Calculate total salary after bonuses and deductions
     */
    public function calculateTotalNet(): string
    {
        $net = (float) $this->totalSalaireBrut + (float) $this->totalPrimes - (float) $this->totalDeductions;
        return number_format($net, 2, '.', '');
    }
}
