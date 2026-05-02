<?php

namespace App\Tests\Service\Conger;

use App\Entity\Rh\Employee;
use App\Entity\Rh\LeaveNotification;
use App\Entity\Rh\LeaveRequest;
use App\Entity\Rh\User;
use App\Repository\Rh\UserRepository;
use App\Service\Rh\LeaveNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class LeaveNotificationServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private UserRepository&MockObject         $userRepository;
    private LeaveNotificationService           $service;

    protected function setUp(): void
    {
        $this->em             = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);

        $this->service = new LeaveNotificationService($this->em, $this->userRepository);
    }

    // ──────────────────── notifyRhNewRequest ─────────────────────────────

    public function testNotifyRhNewRequestNeRienFaitQuandEmployeNull(): void
    {
        $leave = $this->createLeave(employee: null);

        $this->em->expects($this->never())->method('persist');

        $this->service->notifyRhNewRequest($leave);
    }

    public function testNotifyRhNewRequestNeRienFaitQuandRhUserIntrouvable(): void
    {
        $employee = $this->createEmployee(1, 'Alice Dupont', rhId: 10);
        $leave    = $this->createLeave(employee: $employee);

        $this->userRepository->method('find')->willReturn(null);
        $this->em->expects($this->never())->method('persist');

        $this->service->notifyRhNewRequest($leave);
    }

    public function testNotifyRhNewRequestCreeNotificationPourRh(): void
    {
        $employee = $this->createEmployee(1, 'Bob Martin', rhId: 10);
        $rhUser   = $this->createUser(10);
        $leave    = $this->createLeave(employee: $employee, category: 'NORMAL');

        $this->userRepository->method('find')->willReturn($rhUser);

        $this->em->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(LeaveNotification::class));
        $this->em->expects($this->once())->method('flush');

        $this->service->notifyRhNewRequest($leave);
    }

    public function testNotifyRhNewRequestCreeNotificationExceptionnellePourRh(): void
    {
        $employee = $this->createEmployee(1, 'Charlie Ben', rhId: 10);
        $rhUser   = $this->createUser(10);
        $leave    = $this->createLeave(employee: $employee, category: 'EXCEPTION');

        $this->userRepository->method('find')->willReturn($rhUser);

        $persistedNotification = null;
        $this->em->expects($this->once())->method('persist')
            ->willReturnCallback(function ($notification) use (&$persistedNotification) {
                $persistedNotification = $notification;
            });
        $this->em->expects($this->once())->method('flush');

        $this->service->notifyRhNewRequest($leave);

        $this->assertInstanceOf(LeaveNotification::class, $persistedNotification);
        $this->assertStringContainsString('exceptionnelle', $persistedNotification->getTitle());
    }

    // ──────────────────── notifyAdminExceptionPending ────────────────────

    public function testNotifyAdminExceptionPendingNeRienFaitQuandEmployeNull(): void
    {
        $leave = $this->createLeave(employee: null);

        $this->em->expects($this->never())->method('persist');

        $this->service->notifyAdminExceptionPending($leave);
    }

    public function testNotifyAdminExceptionPendingCreeNotificationParAdmin(): void
    {
        $employee = $this->createEmployee(1, 'Diane Lefebvre', rhId: 5);
        $admin1   = $this->createUser(20);
        $admin2   = $this->createUser(21);
        $leave    = $this->createLeave(employee: $employee, category: 'EXCEPTION', urgencyLevel: 'HIGH');

        $this->userRepository->method('findBy')->willReturn([$admin1, $admin2]);

        // 2 admins → 2 persist + 1 flush
        $this->em->expects($this->exactly(2))->method('persist')
            ->with($this->isInstanceOf(LeaveNotification::class));
        $this->em->expects($this->once())->method('flush');

        $this->service->notifyAdminExceptionPending($leave);
    }

    public function testNotifyAdminExceptionPendingIgnoreAdminsSansId(): void
    {
        $employee  = $this->createEmployee(1, 'Eric Morel', rhId: 5);
        $adminNull = $this->createUser(null);
        $leave     = $this->createLeave(employee: $employee, category: 'EXCEPTION');

        $this->userRepository->method('findBy')->willReturn([$adminNull]);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->service->notifyAdminExceptionPending($leave);
    }

    // ──────────────────── notifyEmployeeApproved ─────────────────────────

    public function testNotifyEmployeeApprovedNeRienFaitQuandEmployeNull(): void
    {
        $leave = $this->createLeave(employee: null);

        $this->em->expects($this->never())->method('persist');

        $this->service->notifyEmployeeApproved($leave);
    }

    public function testNotifyEmployeeApprovedCreeNotificationNormale(): void
    {
        $employee = $this->createEmployee(3, 'Farah Kamel', rhId: 10);
        $leave    = $this->createLeave(employee: $employee, category: 'NORMAL');

        $persistedNotification = null;
        $this->em->expects($this->once())->method('persist')
            ->willReturnCallback(function ($n) use (&$persistedNotification) {
                $persistedNotification = $n;
            });
        $this->em->expects($this->once())->method('flush');

        $this->service->notifyEmployeeApproved($leave);

        $this->assertInstanceOf(LeaveNotification::class, $persistedNotification);
        $this->assertSame(LeaveNotification::TYPE_LEAVE_APPROVED, $persistedNotification->getType());
        $this->assertSame(LeaveNotification::RECIPIENT_EMPLOYEE, $persistedNotification->getRecipientType());
        $this->assertSame(3, $persistedNotification->getRecipientId());
    }

    public function testNotifyEmployeeApprovedCreeNotificationExceptionnelle(): void
    {
        $employee = $this->createEmployee(4, 'Gabriel Slim', rhId: 10);
        $leave    = $this->createLeave(employee: $employee, category: 'EXCEPTION');

        $persistedNotification = null;
        $this->em->expects($this->once())->method('persist')
            ->willReturnCallback(function ($n) use (&$persistedNotification) {
                $persistedNotification = $n;
            });
        $this->em->expects($this->once())->method('flush');

        $this->service->notifyEmployeeApproved($leave);

        $this->assertSame(LeaveNotification::TYPE_EXCEPTION_APPROVED, $persistedNotification->getType());
    }

    // ──────────────────── notifyEmployeeRejected ─────────────────────────

    public function testNotifyEmployeeRejectedNeRienFaitQuandEmployeNull(): void
    {
        $leave = $this->createLeave(employee: null);

        $this->em->expects($this->never())->method('persist');

        $this->service->notifyEmployeeRejected($leave, 'Motif de refus');
    }

    public function testNotifyEmployeeRejectedCreeNotificationNormale(): void
    {
        $employee = $this->createEmployee(5, 'Henri Zahra', rhId: 10);
        $leave    = $this->createLeave(employee: $employee, category: 'NORMAL');

        $persistedNotification = null;
        $this->em->expects($this->once())->method('persist')
            ->willReturnCallback(function ($n) use (&$persistedNotification) {
                $persistedNotification = $n;
            });
        $this->em->expects($this->once())->method('flush');

        $this->service->notifyEmployeeRejected($leave, 'Periode bloquee');

        $this->assertInstanceOf(LeaveNotification::class, $persistedNotification);
        $this->assertSame(LeaveNotification::TYPE_LEAVE_REJECTED, $persistedNotification->getType());
        $this->assertStringContainsString('Periode bloquee', $persistedNotification->getMessage());
    }

    public function testNotifyEmployeeRejectedCreeNotificationExceptionnelle(): void
    {
        $employee = $this->createEmployee(6, 'Iris Ben Amor', rhId: 10);
        $leave    = $this->createLeave(employee: $employee, category: 'EXCEPTION');

        $persistedNotification = null;
        $this->em->expects($this->once())->method('persist')
            ->willReturnCallback(function ($n) use (&$persistedNotification) {
                $persistedNotification = $n;
            });
        $this->em->expects($this->once())->method('flush');

        $this->service->notifyEmployeeRejected($leave, 'Justificatif invalide');

        $this->assertSame(LeaveNotification::TYPE_EXCEPTION_REJECTED, $persistedNotification->getType());
    }

    // ──────────────────────── Helpers ────────────────────────────────────

    private function createEmployee(int $id, string $fullName, int $rhId): Employee&MockObject
    {
        $employee = $this->createMock(Employee::class);
        $employee->method('getId')->willReturn($id);
        $employee->method('getFullName')->willReturn($fullName);
        $employee->method('getRhId')->willReturn($rhId);
        return $employee;
    }

    private function createUser(?int $id): User&MockObject
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        return $user;
    }

    private function createLeave(
        ?Employee $employee,
        string $category = 'NORMAL',
        ?string $urgencyLevel = null,
    ): LeaveRequest {
        $leave = new LeaveRequest();
        $leave->setEmployee($employee)
            ->setEmployeeName($employee?->getFullName() ?? 'Inconnu')
            ->setStartDate(new \DateTime('+5 days'))
            ->setEndDate(new \DateTime('+8 days'))
            ->setDaysCount(3)
            ->setLeaveType('Conge annuel')
            ->setStatus('ATTENTE')
            ->setRequestDate(new \DateTime('today'))
            ->setRequestCategory($category);

        if ($urgencyLevel !== null) {
            $leave->setUrgencyLevel($urgencyLevel);
        }

        return $leave;
    }
}
