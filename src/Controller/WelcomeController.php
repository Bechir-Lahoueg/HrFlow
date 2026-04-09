<?php

namespace App\Controller;

use App\Service\LeaveBalanceService;
use App\Service\LeaveRequestService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class WelcomeController extends AbstractController
{
    #[Route('/welcome', name: 'app_welcome')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_welcome_admin');
        }

        if ($this->isGranted('ROLE_RH')) {
            return $this->redirectToRoute('app_welcome_rh');
        }

        if ($this->isGranted('ROLE_EMPLOYEE')) {
            return $this->redirectToRoute('app_welcome_employee');
        }

        if ($this->isGranted('ROLE_CANDIDATE')) {
            return $this->redirectToRoute('app_candidate_dashboard');
        }

        throw $this->createAccessDeniedException('Role not supported for welcome page.');
    }

    #[Route('/welcome/admin', name: 'app_welcome_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(): Response
    {
        return $this->render('DashboardAdmin/welcome_admin.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/welcome/rh', name: 'app_welcome_rh')]
    #[IsGranted('ROLE_RH')]
    public function rh(Connection $connection, LeaveRequestService $leaveRequestService): Response
    {
        $rhId = (int) $this->getUser()?->getId();

        $employeeCount = 0;
        $pendingLeaveCount = 0;

        try {
            $employeeCount = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM employees WHERE rh_id = :rhId',
                ['rhId' => $rhId]
            );
            $pendingLeaveCount = $leaveRequestService->getRhPendingCount($rhId);
        } catch (\Throwable) {
            // Keep dashboard available even if leave tables are not yet provisioned.
        }

        return $this->render('DashboardHr/welcome_rh.html.twig', [
            'user' => $this->getUser(),
            'employeeCount' => $employeeCount,
            'pendingLeaveCount' => $pendingLeaveCount,
        ]);
    }

    #[Route('/welcome/employee', name: 'app_welcome_employee')]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function employee(LeaveRequestService $leaveRequestService, LeaveBalanceService $leaveBalanceService): Response
    {
        $employeeId = (int) $this->getUser()?->getId();

        $pendingLeaveCount = 0;
        $availableLeaveDays = 0.0;
        $balance = [
            'available_days' => 0.0,
            'total_accrued' => 0.0,
            'total_used' => 0.0,
        ];
        $leaveStats = [
            'pending_count' => 0,
            'approved_count' => 0,
            'rejected_count' => 0,
        ];

        try {
            $pendingLeaveCount = $leaveRequestService->getEmployeePendingCount($employeeId);
            $balance = $leaveBalanceService->getEmployeeBalance($employeeId);
            $availableLeaveDays = (float) ($balance['available_days'] ?? 0);
            $leaveStats = $leaveRequestService->getEmployeeDashboardStats($employeeId);
        } catch (\Throwable) {
            // Keep dashboard available even if leave tables are not yet provisioned.
        }

        return $this->render('DashboardEmployee/welcome_employee.html.twig', [
            'user' => $this->getUser(),
            'pendingLeaveCount' => $pendingLeaveCount,
            'availableLeaveDays' => $availableLeaveDays,
            'balance' => $balance,
            'leaveStats' => $leaveStats,
        ]);
    }
}
