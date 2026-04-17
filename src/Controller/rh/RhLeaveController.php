<?php

namespace App\Controller\rh;

use App\Repository\Rh\EmployeeRepository;
use App\Security\DbUser;
use App\Service\Rh\LeaveBalanceService;
use App\Service\Rh\LeaveRequestService;
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
        $search = trim((string) $request->query->get('q', ''));
        $sort = $this->normalizeSort((string) $request->query->get('sort', 'request_date'));
        $direction = $this->normalizeDirection((string) $request->query->get('dir', 'DESC'));

        $employees = $employeeRepository->findBy(['rhId' => $rhId], ['firstName' => 'ASC', 'lastName' => 'ASC']);

        // Refresh accruals and build balance map keyed by employee ID
        $balances = $leaveBalanceService->getBalancesByRh($rhId);
        $balanceMap = [];
        foreach ($balances as $lb) {
            $balanceMap[$lb->getEmployee()->getId()] = $lb->getAvailableDays();
        }

        return $this->render('DashboardHr/Congé/leave_requests.html.twig', [
            'user' => $this->getUser(),
            'leaveRequests' => $leaveRequestService->getRhRequests($rhId, $status, $employeeSearch, $leaveTypeSearch, $search, $sort, $direction),
            'balanceMap' => $balanceMap,
            'rhLeaveStats' => $leaveRequestService->getRhDashboardStats($rhId),
            'rhCreditStats' => $leaveRequestService->getRhCreditSummary($rhId),
            'pendingLeaveCount' => $leaveRequestService->getRhPendingCount($rhId),
            'upcomingLeaves' => $leaveRequestService->getUpcomingApprovedByRh($rhId),
            'statusFilter' => $status,
            'employeeFilter' => $employeeSearch,
            'leaveTypeFilter' => $leaveTypeSearch,
            'searchFilter' => $search,
            'sortFilter' => $sort,
            'dirFilter' => $direction,
            'employees' => $employees,
        ]);
    }

    #[Route('/welcome/rh/leaves/{id}/approve', name: 'app_rh_leave_approve', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function approve(string $id, Request $request, LeaveRequestService $leaveRequestService): RedirectResponse
    {
        $idInt = (int) $id;
        if (!$this->isCsrfTokenValid('rh_leave_approve_' . $idInt, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_leave_requests');
        }

        $comment = trim((string) $request->request->get('rh_comment', ''));
        $result = $leaveRequestService->approveRequestByRh($this->getCurrentRhId(), $idInt, $comment, (string) $this->getUser()?->getUserIdentifier());

        $this->addFlash($result['success'] ? 'success' : 'error', (string) $result['message']);
        return $this->redirectToRoute('app_rh_leave_requests');
    }

    #[Route('/welcome/rh/leaves/{id}/reject', name: 'app_rh_leave_reject', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function reject(string $id, Request $request, LeaveRequestService $leaveRequestService): RedirectResponse
    {
        $idInt = (int) $id;
        if (!$this->isCsrfTokenValid('rh_leave_reject_' . $idInt, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_leave_requests');
        }

        $comment = trim((string) $request->request->get('rh_comment', ''));
        $result = $leaveRequestService->rejectRequestByRh($this->getCurrentRhId(), $idInt, $comment, (string) $this->getUser()?->getUserIdentifier());

        $this->addFlash($result['success'] ? 'success' : 'error', (string) $result['message']);
        return $this->redirectToRoute('app_rh_leave_requests');
    }

    #[Route('/welcome/rh/leaves/credit/grant', name: 'app_rh_leave_credit_grant', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function grantCredit(Request $request, LeaveBalanceService $leaveBalanceService): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('rh_leave_credit_grant', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_leave_requests');
        }

        $employeeId = (int) $request->request->get('employee_id', 0);
        $daysRaw = str_replace(',', '.', trim((string) $request->request->get('credit_days', '0')));
        $creditDays = is_numeric($daysRaw) ? (float) $daysRaw : 0.0;

        if ($employeeId <= 0) {
            $this->addFlash('error', 'Veuillez selectionner un employe.');
            return $this->redirectToRoute('app_rh_leave_requests');
        }

        if ($creditDays <= 0) {
            $this->addFlash('error', 'Le nombre de jours doit etre superieur a 0.');
            return $this->redirectToRoute('app_rh_leave_requests');
        }

        $result = $leaveBalanceService->grantManualCreditByRh($this->getCurrentRhId(), $employeeId, $creditDays);
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

    private function normalizeSort(string $sort): string
    {
        $sort = strtolower(trim($sort));
        $allowed = ['request_date', 'start_date', 'days', 'employee', 'status'];

        return in_array($sort, $allowed, true) ? $sort : 'request_date';
    }

    private function normalizeDirection(string $direction): string
    {
        return strtoupper(trim($direction)) === 'ASC' ? 'ASC' : 'DESC';
    }
}
