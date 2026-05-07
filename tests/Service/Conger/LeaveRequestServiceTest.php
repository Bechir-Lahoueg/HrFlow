<?php

namespace App\Tests\Service\Conger;

use App\Entity\Rh\Employee;
use App\Entity\Rh\LeaveRequest;
use App\Repository\Rh\EmployeeRepository;
use App\Repository\Rh\LeaveRequestRepository;
use App\Service\Rh\LeaveBalanceService;
use App\Service\Rh\LeaveNotificationService;
use App\Service\Rh\LeaveRequestService;
use App\Service\Rh\PublicHolidayService;
use App\Service\Shared\HrFlowMailer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class LeaveRequestServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject    $em;
    private LeaveRequestRepository&MockObject    $leaveRequestRepository;
    private EmployeeRepository&MockObject        $employeeRepository;
    private PublicHolidayService&MockObject      $publicHolidayService;
    private LeaveBalanceService&MockObject       $leaveBalanceService;
    private HrFlowMailer&MockObject             $hrFlowMailer;
    private LeaveNotificationService&MockObject  $leaveNotificationService;
    private LeaveRequestService                  $service;

    protected function setUp(): void
    {
        $this->em                       = $this->createMock(EntityManagerInterface::class);
        $this->leaveRequestRepository   = $this->createMock(LeaveRequestRepository::class);
        $this->employeeRepository       = $this->createMock(EmployeeRepository::class);
        $this->publicHolidayService     = $this->createMock(PublicHolidayService::class);
        $this->leaveBalanceService      = $this->createMock(LeaveBalanceService::class);
        $this->hrFlowMailer             = $this->createMock(HrFlowMailer::class);
        $this->leaveNotificationService = $this->createMock(LeaveNotificationService::class);

        $this->service = new LeaveRequestService(
            $this->em,
            $this->leaveRequestRepository,
            $this->employeeRepository,
            $this->publicHolidayService,
            $this->leaveBalanceService,
            $this->hrFlowMailer,
            $this->leaveNotificationService,
        );
    }

    // ─────────────── submitEmployeeRequest : validations ──────────────────

    public function testSubmitEchoueAvecFormatDateInvalide(): void
    {
        $result = $this->service->submitEmployeeRequest(1, 'not-a-date', '2099-12-31', 'Conge annuel', 'motif');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('date', strtolower($result['message']));
    }

    public function testSubmitEchoueQuandDateDebutDansLePasse(): void
    {
        $past = (new \DateTimeImmutable('-5 days'))->format('Y-m-d');
        $end  = (new \DateTimeImmutable('+5 days'))->format('Y-m-d');

        $result = $this->service->submitEmployeeRequest(1, $past, $end, 'Conge annuel', 'motif');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('pass', strtolower($result['message']));
    }

    public function testSubmitEchoueQuandDateFinAvantDateDebut(): void
    {
        $start = (new \DateTimeImmutable('+10 days'))->format('Y-m-d');
        $end   = (new \DateTimeImmutable('+5 days'))->format('Y-m-d');

        $result = $this->service->submitEmployeeRequest(1, $start, $end, 'Conge annuel', 'motif');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('fin', strtolower($result['message']));
    }

    public function testSubmitEchoueQuandChevauchementAvecDemandeExistante(): void
    {
        $start = (new \DateTimeImmutable('+5 days'))->format('Y-m-d');
        $end   = (new \DateTimeImmutable('+10 days'))->format('Y-m-d');

        $this->leaveRequestRepository->method('hasDateOverlap')->willReturn(true);

        $result = $this->service->submitEmployeeRequest(1, $start, $end, 'Conge annuel', 'motif');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('chevauche', strtolower($result['message']));
    }

    public function testSubmitEchoueQuandJourFeriesDansLaPeriode(): void
    {
        $start = (new \DateTimeImmutable('+5 days'))->format('Y-m-d');
        $end   = (new \DateTimeImmutable('+10 days'))->format('Y-m-d');

        $this->leaveRequestRepository->method('hasDateOverlap')->willReturn(false);
        $this->publicHolidayService->method('hasHolidayInRange')->willReturn(true);

        $result = $this->service->submitEmployeeRequest(1, $start, $end, 'Conge annuel', 'motif');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('ferie', strtolower($result['message']));
    }

    public function testSubmitEchoueQuandAucunJourOuvrable(): void
    {
        $start = (new \DateTimeImmutable('+5 days'))->format('Y-m-d');
        $end   = (new \DateTimeImmutable('+10 days'))->format('Y-m-d');

        $this->leaveRequestRepository->method('hasDateOverlap')->willReturn(false);
        $this->publicHolidayService->method('hasHolidayInRange')->willReturn(false);
        $this->publicHolidayService->method('countWorkingDays')->willReturn(0);

        $result = $this->service->submitEmployeeRequest(1, $start, $end, 'Conge annuel', 'motif');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('ouvrable', strtolower($result['message']));
    }

    public function testSubmitEchoueQuandSoldeInsuffisantPourDemandeNormale(): void
    {
        $start = (new \DateTimeImmutable('+5 days'))->format('Y-m-d');
        $end   = (new \DateTimeImmutable('+10 days'))->format('Y-m-d');

        $this->leaveRequestRepository->method('hasDateOverlap')->willReturn(false);
        $this->publicHolidayService->method('hasHolidayInRange')->willReturn(false);
        $this->publicHolidayService->method('countWorkingDays')->willReturn(4);
        $this->leaveBalanceService->method('getEmployeeBalance')->willReturn([
            'available_days' => 2.0, 'total_accrued' => 10.0, 'total_used' => 8.0,
        ]);

        $result = $this->service->submitEmployeeRequest(1, $start, $end, 'Conge annuel', 'motif');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('insuffisant', strtolower($result['message']));
    }

    public function testSubmitEchoueQuandSoldeZeroPourDemandeNormale(): void
    {
        $start = (new \DateTimeImmutable('+5 days'))->format('Y-m-d');
        $end   = (new \DateTimeImmutable('+10 days'))->format('Y-m-d');

        $this->leaveRequestRepository->method('hasDateOverlap')->willReturn(false);
        $this->publicHolidayService->method('hasHolidayInRange')->willReturn(false);
        $this->publicHolidayService->method('countWorkingDays')->willReturn(3);
        $this->leaveBalanceService->method('getEmployeeBalance')->willReturn([
            'available_days' => 0.0, 'total_accrued' => 0.0, 'total_used' => 0.0,
        ]);

        $result = $this->service->submitEmployeeRequest(1, $start, $end, 'Conge annuel', 'motif');

        $this->assertFalse($result['success']);
    }

    // ─────────────── submitEmployeeRequest : demande normale OK ───────────

    public function testSubmitDemandeNormaleAvecSucces(): void
    {
        $start    = (new \DateTimeImmutable('+5 days'))->format('Y-m-d');
        $end      = (new \DateTimeImmutable('+8 days'))->format('Y-m-d');
        $employee = $this->createEmployee(1, 'Henri Dupont');

        $this->leaveRequestRepository->method('hasDateOverlap')->willReturn(false);
        $this->publicHolidayService->method('hasHolidayInRange')->willReturn(false);
        $this->publicHolidayService->method('countWorkingDays')->willReturn(3);
        $this->leaveBalanceService->method('getEmployeeBalance')->willReturn([
            'available_days' => 10.0, 'total_accrued' => 21.6, 'total_used' => 11.6,
        ]);
        $this->employeeRepository->method('find')->willReturn($employee);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->hrFlowMailer->expects($this->once())->method('sendNewRequestNotification');
        $this->leaveNotificationService->expects($this->once())->method('notifyRhNewRequest');

        $result = $this->service->submitEmployeeRequest(1, $start, $end, 'Conge annuel', 'motif');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('3 jour', $result['message']);
    }

    // ─────────────── submitEmployeeRequest : demande exceptionnelle ────────

    public function testSubmitExceptionEchoueQuandMotifTropCourt(): void
    {
        $start = (new \DateTimeImmutable('+5 days'))->format('Y-m-d');
        $end   = (new \DateTimeImmutable('+8 days'))->format('Y-m-d');

        $this->leaveRequestRepository->method('hasDateOverlap')->willReturn(false);
        $this->publicHolidayService->method('hasHolidayInRange')->willReturn(false);
        $this->publicHolidayService->method('countWorkingDays')->willReturn(3);
        $this->leaveBalanceService->method('getEmployeeBalance')->willReturn([
            'available_days' => 0.0, 'total_accrued' => 0.0, 'total_used' => 0.0,
        ]);
        $this->employeeRepository->method('find')->willReturn($this->createEmployee(1, 'Iris Ben'));

        $result = $this->service->submitEmployeeRequest(
            1, $start, $end, 'Urgence', 'court', // motif < 15 chars
            'EXCEPTION', 'HIGH'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('15', $result['message']);
    }

    public function testSubmitExceptionEchoueAvecNiveauUrgenceInvalide(): void
    {
        $start = (new \DateTimeImmutable('+5 days'))->format('Y-m-d');
        $end   = (new \DateTimeImmutable('+8 days'))->format('Y-m-d');

        $this->leaveRequestRepository->method('hasDateOverlap')->willReturn(false);
        $this->publicHolidayService->method('hasHolidayInRange')->willReturn(false);
        $this->publicHolidayService->method('countWorkingDays')->willReturn(3);
        $this->leaveBalanceService->method('getEmployeeBalance')->willReturn([
            'available_days' => 0.0, 'total_accrued' => 0.0, 'total_used' => 0.0,
        ]);
        $this->employeeRepository->method('find')->willReturn($this->createEmployee(1, 'Jade Martin'));

        $result = $this->service->submitEmployeeRequest(
            1, $start, $end, 'Urgence médicale',
            'Urgence medicale grave raison suffisamment longue',
            'EXCEPTION', 'INVALID_LEVEL'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('urgence', strtolower($result['message']));
    }

    public function testSubmitExceptionAvecSucces(): void
    {
        $start    = (new \DateTimeImmutable('+5 days'))->format('Y-m-d');
        $end      = (new \DateTimeImmutable('+8 days'))->format('Y-m-d');
        $employee = $this->createEmployee(1, 'Kevin Diallo');

        $this->leaveRequestRepository->method('hasDateOverlap')->willReturn(false);
        $this->publicHolidayService->method('hasHolidayInRange')->willReturn(false);
        $this->publicHolidayService->method('countWorkingDays')->willReturn(3);
        $this->leaveBalanceService->method('getEmployeeBalance')->willReturn([
            'available_days' => 0.0, 'total_accrued' => 0.0, 'total_used' => 0.0,
        ]);
        $this->employeeRepository->method('find')->willReturn($employee);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->hrFlowMailer->expects($this->once())->method('sendNewRequestNotification');

        $result = $this->service->submitEmployeeRequest(
            1, $start, $end, 'Urgence médicale',
            'Urgence medicale grave et documentee avec justificatif',
            'EXCEPTION', 'HIGH'
        );

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('exceptionnelle', strtolower($result['message']));
    }

    // ─────────────── deleteEmployeePendingRequest ─────────────────────────

    public function testDeleteRetourneFalseQuandDemandeIntrouvable(): void
    {
        $this->leaveRequestRepository->method('findOnePendingByEmployee')->willReturn(null);

        $result = $this->service->deleteEmployeePendingRequest(1, 99);

        $this->assertFalse($result);
    }

    public function testDeleteSupprimeLaDemandeAvecSucces(): void
    {
        $request = $this->createLeaveRequest();

        $this->leaveRequestRepository->method('findOnePendingByEmployee')->willReturn($request);
        $this->em->expects($this->once())->method('remove')->with($request);
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->deleteEmployeePendingRequest(1, 1);

        $this->assertTrue($result);
    }

    // ─────────────── approveRequestByRh ──────────────────────────────────

    public function testApproveByRhRetourneFalseQuandDemandeIntrouvable(): void
    {
        $this->leaveRequestRepository->method('findOneByRh')->willReturn(null);

        $result = $this->service->approveRequestByRh(10, 99);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('introuvable', strtolower($result['message']));
    }

    public function testApproveByRhRetourneFalseQuandStatutNonAttente(): void
    {
        $request = $this->createLeaveRequest(status: 'ACCEPTE');
        $this->leaveRequestRepository->method('findOneByRh')->willReturn($request);

        $result = $this->service->approveRequestByRh(10, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('attente', strtolower($result['message']));
    }

    public function testApproveByRhDemandeNormaleAvecSucces(): void
    {
        $employee = $this->createEmployee(1, 'Laura Benali');
        $request  = $this->createLeaveRequest(
            status: 'ATTENTE',
            category: LeaveRequestService::CATEGORY_NORMAL,
            daysCount: 3,
            employee: $employee
        );

        $this->leaveRequestRepository->method('findOneByRh')->willReturn($request);
        $this->leaveBalanceService->method('deductApprovedDays')->willReturn(true);
        $this->em->expects($this->once())->method('flush');
        $this->hrFlowMailer->expects($this->once())->method('sendLeaveDecision');
        $this->leaveNotificationService->expects($this->once())->method('notifyEmployeeApproved');

        $result = $this->service->approveRequestByRh(10, 1, 'OK');

        $this->assertTrue($result['success']);
    }

    public function testApproveByRhDemandeExceptionnellePasseEnAdminPending(): void
    {
        $employee = $this->createEmployee(1, 'Marc Rousseau');
        $request  = $this->createLeaveRequest(
            status: 'ATTENTE',
            category: LeaveRequestService::CATEGORY_EXCEPTION,
            workflowStatus: LeaveRequestService::WORKFLOW_RH_PENDING,
            daysCount: 5,
            employee: $employee
        );

        $this->leaveRequestRepository->method('findOneByRh')->willReturn($request);
        $this->em->expects($this->once())->method('flush');
        $this->hrFlowMailer->expects($this->once())->method('sendExceptionPendingAdmin');
        $this->leaveNotificationService->expects($this->once())->method('notifyAdminExceptionPending');

        $result = $this->service->approveRequestByRh(10, 1, 'Pre-approuve');

        $this->assertTrue($result['success']);
        $this->assertSame(LeaveRequestService::WORKFLOW_ADMIN_PENDING, $request->getWorkflowStatus());
    }

    // ─────────────── rejectRequestByRh ───────────────────────────────────

    public function testRejectByRhEchoueAvecCommentaireVide(): void
    {
        $employee = $this->createEmployee(1, 'Nina Karim');
        $request  = $this->createLeaveRequest(status: 'ATTENTE', employee: $employee);
        $this->leaveRequestRepository->method('findOneByRh')->willReturn($request);

        $result = $this->service->rejectRequestByRh(10, 1, '');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('commentaire', strtolower($result['message']));
    }

    public function testRejectByRhAvecSucces(): void
    {
        $employee = $this->createEmployee(1, 'Omar Sfax');
        $request  = $this->createLeaveRequest(
            status: 'ATTENTE',
            category: LeaveRequestService::CATEGORY_NORMAL,
            employee: $employee
        );
        $this->leaveRequestRepository->method('findOneByRh')->willReturn($request);
        $this->em->expects($this->once())->method('flush');
        $this->hrFlowMailer->expects($this->once())->method('sendLeaveDecision');
        $this->leaveNotificationService->expects($this->once())->method('notifyEmployeeRejected');

        $result = $this->service->rejectRequestByRh(10, 1, 'Periode de conge bloquee');

        $this->assertTrue($result['success']);
        $this->assertSame('REFUSE', $request->getStatus());
    }

    // ─────────────── approveExceptionByAdmin ─────────────────────────────

    public function testApproveExceptionByAdminEchoueQuandDemandeIntrouvable(): void
    {
        $this->leaveRequestRepository->method('find')->willReturn(null);

        $result = $this->service->approveExceptionByAdmin(99);

        $this->assertFalse($result['success']);
    }

    public function testApproveExceptionByAdminEchoueQuandWorkflowNonAdminPending(): void
    {
        $employee = $this->createEmployee(1, 'Pauline Tunis');
        $request  = $this->createLeaveRequest(
            status: 'ATTENTE',
            category: LeaveRequestService::CATEGORY_EXCEPTION,
            workflowStatus: LeaveRequestService::WORKFLOW_RH_PENDING, // pas encore ADMIN_PENDING
            daysCount: 3,
            employee: $employee
        );
        $this->leaveRequestRepository->method('find')->willReturn($request);

        $result = $this->service->approveExceptionByAdmin(1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('admin', strtolower($result['message']));
    }

    public function testApproveExceptionByAdminAvecSucces(): void
    {
        $employee = $this->createEmployee(1, 'Rachid Ben Salem');
        $request  = $this->createLeaveRequest(
            status: 'ATTENTE',
            category: LeaveRequestService::CATEGORY_EXCEPTION,
            workflowStatus: LeaveRequestService::WORKFLOW_ADMIN_PENDING,
            daysCount: 5,
            employee: $employee
        );
        $this->leaveRequestRepository->method('find')->willReturn($request);
        $this->leaveBalanceService->method('deductApprovedDays')->willReturn(true);
        $this->em->expects($this->once())->method('flush');
        $this->hrFlowMailer->expects($this->once())->method('sendLeaveDecision');
        $this->leaveNotificationService->expects($this->once())->method('notifyEmployeeApproved');

        $result = $this->service->approveExceptionByAdmin(1, 'Approuve', 'ADMIN');

        $this->assertTrue($result['success']);
        $this->assertSame('ACCEPTE', $request->getStatus());
        $this->assertSame(LeaveRequestService::WORKFLOW_ADMIN_APPROVED, $request->getWorkflowStatus());
    }

    // ─────────────── rejectExceptionByAdmin ──────────────────────────────

    public function testRejectExceptionByAdminEchoueAvecCommentaireVide(): void
    {
        $employee = $this->createEmployee(1, 'Sara Arbi');
        $request  = $this->createLeaveRequest(
            status: 'ATTENTE',
            category: LeaveRequestService::CATEGORY_EXCEPTION,
            workflowStatus: LeaveRequestService::WORKFLOW_ADMIN_PENDING,
            daysCount: 3,
            employee: $employee
        );
        $this->leaveRequestRepository->method('find')->willReturn($request);

        $result = $this->service->rejectExceptionByAdmin(1, '');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('commentaire', strtolower($result['message']));
    }

    public function testRejectExceptionByAdminAvecSucces(): void
    {
        $employee = $this->createEmployee(1, 'Tarek Sfax');
        $request  = $this->createLeaveRequest(
            status: 'ATTENTE',
            category: LeaveRequestService::CATEGORY_EXCEPTION,
            workflowStatus: LeaveRequestService::WORKFLOW_ADMIN_PENDING,
            daysCount: 3,
            employee: $employee
        );
        $this->leaveRequestRepository->method('find')->willReturn($request);
        $this->em->expects($this->once())->method('flush');
        $this->hrFlowMailer->expects($this->once())->method('sendLeaveDecision');
        $this->leaveNotificationService->expects($this->once())->method('notifyEmployeeRejected');

        $result = $this->service->rejectExceptionByAdmin(1, 'Motif insuffisant', 'ADMIN');

        $this->assertTrue($result['success']);
        $this->assertSame('REFUSE', $request->getStatus());
        $this->assertSame(LeaveRequestService::WORKFLOW_ADMIN_REJECTED, $request->getWorkflowStatus());
    }

    // ──────────────────────── Helpers ────────────────────────────────────

    private function createEmployee(int $id, string $fullName, int $rhId = 10): Employee&MockObject
    {
        $employee = $this->createMock(Employee::class);
        $employee->method('getId')->willReturn($id);
        $employee->method('getFullName')->willReturn($fullName);
        $employee->method('getRhId')->willReturn($rhId);
        return $employee;
    }

    private function createLeaveRequest(
        string $status = 'ATTENTE',
        string $category = LeaveRequestService::CATEGORY_NORMAL,
        string $workflowStatus = LeaveRequestService::WORKFLOW_NORMAL,
        int $daysCount = 3,
        ?Employee $employee = null,
    ): LeaveRequest {
        $request = new LeaveRequest();
        $request->setStatus($status)
            ->setRequestCategory($category)
            ->setWorkflowStatus($workflowStatus)
            ->setDaysCount($daysCount)
            ->setStartDate(new \DateTime('+5 days'))
            ->setEndDate(new \DateTime('+8 days'))
            ->setLeaveType('Conge annuel')
            ->setRequestDate(new \DateTime('today'))
            ->setEmployeeName($employee?->getFullName() ?? 'Test Employe');

        if ($employee !== null) {
            $request->setEmployee($employee);
        }

        return $request;
    }
}
