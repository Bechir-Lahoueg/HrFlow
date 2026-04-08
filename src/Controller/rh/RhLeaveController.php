<?php

namespace App\Controller\rh;

use App\Repository\EmployeeRepository;
use App\Security\DbUser;
use App\Service\LeaveBalanceService;
use App\Service\LeaveRequestService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class RhLeaveController extends AbstractController
{
    #[Route('/welcome/rh/leaves', name: 'app_rh_leave_requests', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function index(
        Request $request,
        LeaveRequestService $leaveRequestService,
        LeaveBalanceService $leaveBalanceService,
        EmployeeRepository $employeeRepository,
    ): Response {
        $rhId = $this->getCurrentRhId();
        $status = $this->normalizeStatus((string) $request->query->get('status', 'ATTENTE'));
        $employeeSearch = trim((string) $request->query->get('employee', ''));
        $leaveTypeSearch = trim((string) $request->query->get('leave_type', ''));

        $employees = $employeeRepository->findBy(['rhId' => $rhId], ['firstName' => 'ASC', 'lastName' => 'ASC']);

        // Refresh accruals and build balance map keyed by employee ID
        $balances = $leaveBalanceService->getBalancesByRh($rhId);
        $balanceMap = [];
        foreach ($balances as $lb) {
            $balanceMap[$lb->getEmployee()->getId()] = $lb->getAvailableDays();
        }

        return $this->render('DashboardHr/leave_requests.html.twig', [
            'user' => $this->getUser(),
            'leaveRequests' => $leaveRequestService->getRhRequests($rhId, $status, $employeeSearch, $leaveTypeSearch),
            'balanceMap' => $balanceMap,
            'rhLeaveStats' => $leaveRequestService->getRhDashboardStats($rhId),
            'rhCreditStats' => $leaveRequestService->getRhCreditSummary($rhId),
            'pendingLeaveCount' => $leaveRequestService->getRhPendingCount($rhId),
            'statusFilter' => $status,
            'employeeFilter' => $employeeSearch,
            'leaveTypeFilter' => $leaveTypeSearch,
            'employees' => $employees,
        ]);
    }

    #[Route('/welcome/rh/leaves/{id}/approve', name: 'app_rh_leave_approve', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function approve(int $id, Request $request, LeaveRequestService $leaveRequestService): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('rh_leave_approve_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_leave_requests');
        }

        $comment = trim((string) $request->request->get('rh_comment', ''));
        $result = $leaveRequestService->approveRequestByRh($this->getCurrentRhId(), $id, $comment);

        $this->addFlash($result['success'] ? 'success' : 'error', (string) $result['message']);
        return $this->redirectToRoute('app_rh_leave_requests');
    }

    #[Route('/welcome/rh/leaves/{id}/reject', name: 'app_rh_leave_reject', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function reject(int $id, Request $request, LeaveRequestService $leaveRequestService): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('rh_leave_reject_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_leave_requests');
        }

        $comment = trim((string) $request->request->get('rh_comment', ''));
        $result = $leaveRequestService->rejectRequestByRh($this->getCurrentRhId(), $id, $comment);

        $this->addFlash($result['success'] ? 'success' : 'error', (string) $result['message']);
        return $this->redirectToRoute('app_rh_leave_requests');
    }

    private function getCurrentRhId(): int
    {
        $user = $this->getUser();

        if (!$user instanceof DbUser) {
            throw $this->createAccessDeniedException('Utilisateur RH invalide.');
        }

        return $user->getId();
    }

    private function normalizeStatus(string $status): ?string
    {
        $status = strtoupper(trim($status));

        if ($status === '' || $status === 'ALL') {
            return null;
        }

        return in_array($status, ['ATTENTE', 'ACCEPTE', 'REFUSE'], true) ? $status : 'ATTENTE';
    }
}
