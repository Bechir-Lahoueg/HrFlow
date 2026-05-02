<?php

namespace App\Tests\Service\Conger;

use App\Entity\Rh\Employee;
use App\Entity\Rh\LeaveNotification;
use App\Entity\Rh\LeaveRequest;
use App\Entity\Rh\User;
use App\Repository\Rh\UserRepository;
use App\Service\Rh\LeaveNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LeaveNotificationServiceTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private $em;

    /** @var UserRepository&MockObject */
    private $userRepository;

    private LeaveNotificationService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);

        $this->service = new LeaveNotificationService($this->em, $this->userRepository);
    }

    // ──────────────────── notifyRhNewRequest ─────────────────────────────

    public function testNotifyRhNewRequestNeRienFaitQuandEmployeNull(): void
    {
        $leave = $this->createLeave(null);

        $this->em->expects($this->never())->method('persist');

        $this->service->notifyRhNewRequest($leave);
    }

    public function testNotifyRhNewRequestNeRienFaitQuandRhUserIntrouvable(): void
    {
        $employee = $this->createEmployee(1, 'Alice Dupont', 10);
        $leave    = $this->createLeave($employee);

        $this->userRepository->method('find')->willReturn(null);
        $this->em->expects($this->never())->method('persist');

        $this->service->notifyRhNewRequest($leave);
    }

    public function testNotifyRhNewRequestCreeNotificationPourRh(): void
    {
        $employee = $this->createEmployee(1, 'Bob Martin', 10);
        $rhUser   = $this->createUser(10);
        $leave    = $this->createLeave($employee, 'NORMAL');

        $this->userRepository->method('find')->willReturn($rhUser);

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(LeaveNotification::class));

        $this->em->expects($this->once())->method('flush');

        $this->service->notifyRhNewRequest($leave);
    }

    public function testNotifyRhNewRequestCreeNotificationExceptionnellePourRh(): void
    {
        $employee = $this->createEmployee(1, 'Charlie Ben', 10);
        $rhUser   = $this->createUser(10);
        $leave    = $this->createLeave($employee, 'EXCEPTION');

        $this->userRepository->method('find')->willReturn($rhUser);

        $persistedNotification = null;

        $this->em->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($notification) use (&$persistedNotification) {
                $persistedNotification = $notification;
            });

        $this->em->expects($this->once())->method('flush');

        $this->service->notifyRhNewRequest($leave);

        $this->assertInstanceOf(LeaveNotification::class, $persistedNotification);
        $this->assertStringContainsString('exceptionnelle', strtolower($persistedNotification->getTitle()));
    }

    // ──────────────────── Helpers ────────────────────────────────────────

    private function createEmployee(int $id, string $fullName, int $rhId): Employee
    {
        $employee = $this->createMock(Employee::class);
        $employee->method('getId')->willReturn($id);
        $employee->method('getFullName')->willReturn($fullName);
        $employee->method('getRhId')->willReturn($rhId);

        return $employee;
    }

    private function createUser(?int $id): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);

        return $user;
    }

    private function createLeave(
        ?Employee $employee,
        string $category = 'NORMAL',
        ?string $urgencyLevel = null
    ): LeaveRequest {
        $leave = new LeaveRequest();

        $leave->setEmployee($employee);
        $leave->setEmployeeName($employee ? $employee->getFullName() : 'Inconnu');
        $leave->setStartDate(new \DateTime('+5 days'));
        $leave->setEndDate(new \DateTime('+8 days'));
        $leave->setDaysCount(3);
        $leave->setLeaveType('Conge annuel');
        $leave->setStatus('ATTENTE');
        $leave->setRequestDate(new \DateTime());
        $leave->setRequestCategory($category);

        if ($urgencyLevel !== null) {
            $leave->setUrgencyLevel($urgencyLevel);
        }

        return $leave;
    }
}
