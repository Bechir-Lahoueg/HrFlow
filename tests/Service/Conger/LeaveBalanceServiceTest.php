<?php

namespace App\Tests\Service\Conger;

use App\Entity\Rh\Employee;
use App\Entity\Rh\LeaveBalance;
use App\Repository\Rh\EmployeeRepository;
use App\Repository\Rh\LeaveBalanceRepository;
use App\Service\Rh\LeaveBalanceService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class LeaveBalanceServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private LeaveBalanceRepository&MockObject $leaveBalanceRepository;
    private EmployeeRepository&MockObject $employeeRepository;
    private LeaveBalanceService $service;

    protected function setUp(): void
    {
        $this->em                   = $this->createMock(EntityManagerInterface::class);
        $this->leaveBalanceRepository = $this->createMock(LeaveBalanceRepository::class);
        $this->employeeRepository   = $this->createMock(EmployeeRepository::class);

        $this->service = new LeaveBalanceService(
            $this->em,
            $this->leaveBalanceRepository,
            $this->employeeRepository,
        );
    }

    // ──────────────────────── getEmployeeBalance ──────────────────────────

    public function testGetEmployeeBalanceRetourneZerosQuandBalanceInexistante(): void
    {
        // Pas d'employé → accrueIfNeeded retourne tôt
        $this->employeeRepository->method('find')->willReturn(null);
        $this->leaveBalanceRepository->method('findByEmployee')->willReturn(null);

        $result = $this->service->getEmployeeBalance(99);

        $this->assertSame(0.0, $result['available_days']);
        $this->assertSame(0.0, $result['total_accrued']);
        $this->assertSame(0.0, $result['total_used']);
    }

    public function testGetEmployeeBalanceRetourneLesSoldesExistants(): void
    {
        $employee = $this->createEmployee(1, 'Alice Dupont');
        $balance  = $this->createBalance($employee, 12.5, 21.6, 9.1);

        $this->employeeRepository->method('find')->willReturn($employee);
        $this->leaveBalanceRepository->method('findByEmployee')->willReturn($balance);

        $result = $this->service->getEmployeeBalance(1);

        $this->assertSame(12.5, $result['available_days']);
        $this->assertSame(21.6, $result['total_accrued']);
        $this->assertSame(9.1, $result['total_used']);
    }

    // ──────────────────────── deductApprovedDays ─────────────────────────

    public function testDeductApprovedDaysRetourneFalseQuandBalanceInexistante(): void
    {
        $this->leaveBalanceRepository->method('findByEmployee')->willReturn(null);

        $result = $this->service->deductApprovedDays(1, 5);

        $this->assertFalse($result);
    }

    public function testDeductApprovedDaysRetourneFalseQuandSoldeInsuffisant(): void
    {
        $employee = $this->createEmployee(1, 'Bob Martin');
        $balance  = $this->createBalance($employee, 3.0, 10.0, 7.0);

        $this->leaveBalanceRepository->method('findByEmployee')->willReturn($balance);

        $result = $this->service->deductApprovedDays(1, 5);

        $this->assertFalse($result);
    }

    public function testDeductApprovedDaysDeduitCorrectementLesSoldes(): void
    {
        $employee = $this->createEmployee(1, 'Bob Martin');
        $balance  = $this->createBalance($employee, 10.0, 20.0, 10.0);

        $this->leaveBalanceRepository->method('findByEmployee')->willReturn($balance);
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->deductApprovedDays(1, 5);

        $this->assertTrue($result);
        $this->assertSame(5.0, $balance->getAvailableDays());
        $this->assertSame(15.0, $balance->getTotalUsed());
    }

    public function testDeductApprovedDaysAvecAllowNegativeAccepteDepassement(): void
    {
        $employee = $this->createEmployee(1, 'Charlie Dupont');
        $balance  = $this->createBalance($employee, 2.0, 10.0, 8.0);

        $this->leaveBalanceRepository->method('findByEmployee')->willReturn($balance);
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->deductApprovedDays(1, 5, true);

        $this->assertTrue($result);
        $this->assertSame(-3.0, $balance->getAvailableDays());
    }

    // ──────────────────────── refundApprovedDays ─────────────────────────

    public function testRefundApprovedDaysNeEchouesPasSiBalanceInexistante(): void
    {
        $this->leaveBalanceRepository->method('findByEmployee')->willReturn(null);

        // Ne doit pas lever d'exception
        $this->service->refundApprovedDays(1, 5);
        $this->addToAssertionCount(1);
    }

    public function testRefundApprovedDaysRembourseLeSolde(): void
    {
        $employee = $this->createEmployee(1, 'Diane Lefebvre');
        $balance  = $this->createBalance($employee, 5.0, 20.0, 15.0);

        $this->leaveBalanceRepository->method('findByEmployee')->willReturn($balance);
        $this->em->expects($this->once())->method('flush');

        $this->service->refundApprovedDays(1, 5);

        $this->assertSame(10.0, $balance->getAvailableDays());
        $this->assertSame(10.0, $balance->getTotalUsed());
    }

    public function testRefundApprovedDaysTotalUsedNePasDescendreSousZero(): void
    {
        $employee = $this->createEmployee(1, 'Eric Morel');
        $balance  = $this->createBalance($employee, 5.0, 10.0, 2.0);

        $this->leaveBalanceRepository->method('findByEmployee')->willReturn($balance);
        $this->em->expects($this->once())->method('flush');

        $this->service->refundApprovedDays(1, 10); // remboursement supérieur à used

        $this->assertSame(0.0, $balance->getTotalUsed()); // max(0, ...)
    }

    // ──────────────────────── grantManualCreditByRh ───────────────────────

    public function testGrantManualCreditRetourneFalseQuandCreditNegatif(): void
    {
        $result = $this->service->grantManualCreditByRh(10, 1, -5.0);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('superieur', $result['message']);
    }

    public function testGrantManualCreditRetourneFalseQuandCreditZero(): void
    {
        $result = $this->service->grantManualCreditByRh(10, 1, 0.0);

        $this->assertFalse($result['success']);
    }

    public function testGrantManualCreditRetourneFalseQuandEmployeIntrouvable(): void
    {
        $this->employeeRepository->method('find')->willReturn(null);

        $result = $this->service->grantManualCreditByRh(10, 99, 5.0);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('introuvable', $result['message']);
    }

    public function testGrantManualCreditRetourneFalseQuandEmployeHorsEquipe(): void
    {
        $employee = $this->createEmployee(1, 'Farah Ben Ali', rhId: 99); // appartient à RH 99

        $this->employeeRepository->method('find')->willReturn($employee);

        $result = $this->service->grantManualCreditByRh(10, 1, 5.0); // RH id = 10

        $this->assertFalse($result['success']);
    }

    public function testGrantManualCreditAjouteLeCredit(): void
    {
        $employee = $this->createEmployee(1, 'Gabriel Petit', rhId: 10);
        $balance  = $this->createBalance($employee, 5.0, 15.0, 10.0);

        $this->employeeRepository->method('find')->willReturn($employee);
        $this->leaveBalanceRepository->method('findByEmployee')->willReturn($balance);
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->grantManualCreditByRh(10, 1, 3.5);

        $this->assertTrue($result['success']);
        $this->assertSame(8.5, $balance->getAvailableDays());
        $this->assertSame(18.5, $balance->getTotalAccrued());
    }

    // ──────────────────────── getCreditSummaryByRh ────────────────────────

    public function testGetCreditSummaryByRhRetourneZerosEnCasErreur(): void
    {
        $this->leaveBalanceRepository
            ->method('getCreditSummaryByRh')
            ->willThrowException(new \RuntimeException('DB error'));

        $result = $this->service->getCreditSummaryByRh(1);

        $this->assertSame(0.0, $result['available_sum']);
        $this->assertSame(0.0, $result['used_sum']);
        $this->assertSame(0, $result['employees_count']);
    }

    // ──────────────────────── Helpers ────────────────────────────────────

    private function createEmployee(int $id, string $fullName, int $rhId = 1): Employee
    {
        $employee = $this->createMock(Employee::class);
        $employee->method('getId')->willReturn($id);
        $employee->method('getFullName')->willReturn($fullName);
        $employee->method('getRhId')->willReturn($rhId);
        $employee->method('getCreatedAt')->willReturn(new \DateTime('-2 years'));
        return $employee;
    }

    private function createBalance(Employee $employee, float $available, float $accrued, float $used): LeaveBalance
    {
        $balance = new LeaveBalance();
        $balance->setEmployee($employee)
            ->setEmployeeName($employee->getFullName())
            ->setAvailableDays($available)
            ->setTotalAccrued($accrued)
            ->setTotalUsed($used)
            ->setHireDate(new \DateTimeImmutable('-2 years'))
            ->setLastAccrualDate(new \DateTimeImmutable('today'));
        return $balance;
    }
}
