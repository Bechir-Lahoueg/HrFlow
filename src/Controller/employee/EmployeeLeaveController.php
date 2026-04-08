<?php

namespace App\Controller\employee;

use App\Security\DbUser;
use App\Service\LeaveBalanceService;
use App\Service\PublicHolidayService;
use App\Service\LeaveRequestService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class EmployeeLeaveController extends AbstractController
{
    #[Route('/welcome/employee/leaves', name: 'app_employee_leave_requests', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function index(
        Request $request,
        LeaveRequestService $leaveRequestService,
        LeaveBalanceService $leaveBalanceService,
        PublicHolidayService $publicHolidayService,
    ): Response {
        if ($request->isMethod('POST')) {
            $redirect = $this->handleSubmitRequest($request, $leaveRequestService);
            if ($redirect !== null) {
                return $redirect;
            }
        }

        $employeeId = $this->getCurrentEmployeeId();

        $today = new DateTimeImmutable('today');
        $calendarEnd = $today->modify('+12 months');
        $blockedHolidayDates = $publicHolidayService->getHolidayDatesInRange($today, $calendarEnd);

        sort($blockedHolidayDates);
        $blockedHolidayDates = array_values(array_unique($blockedHolidayDates));

        return $this->render('DashboardEmployee/leave_requests.html.twig', [
            'user' => $this->getUser(),
            'leaveRequests' => $leaveRequestService->getEmployeeRequests($employeeId),
            'balance' => $leaveBalanceService->getEmployeeBalance($employeeId),
            'leaveStats' => $leaveRequestService->getEmployeeDashboardStats($employeeId),
            'pendingLeaveCount' => $leaveRequestService->getEmployeePendingCount($employeeId),
            'blockedHolidayDates' => $blockedHolidayDates,
        ]);
    }

    #[Route('/welcome/employee/leaves/{id}/delete', name: 'app_employee_leave_request_delete', methods: ['POST'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function delete(
        string $id,
        Request $request,
        LeaveRequestService $leaveRequestService,
    ): RedirectResponse {
        $idInt = (int) $id;
        if (!$this->isCsrfTokenValid('employee_leave_delete_' . $idInt, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_employee_leave_requests');
        }

        $deleted = $leaveRequestService->deleteEmployeePendingRequest($this->getCurrentEmployeeId(), $idInt);

        if (!$deleted) {
            $this->addFlash('error', 'Seules les demandes en attente peuvent etre supprimees.');
            return $this->redirectToRoute('app_employee_leave_requests');
        }

        $this->addFlash('success', 'Demande supprimee avec succes.');
        return $this->redirectToRoute('app_employee_leave_requests');
    }

    private function handleSubmitRequest(Request $request, LeaveRequestService $leaveRequestService): ?RedirectResponse
    {
        if (!$this->isCsrfTokenValid('employee_leave_submit', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_employee_leave_requests');
        }

        $startDate = trim((string) $request->request->get('start_date', ''));
        $endDate = trim((string) $request->request->get('end_date', ''));
        $leaveType = trim((string) $request->request->get('leave_type', ''));
        $reason = trim((string) $request->request->get('reason', ''));

        $result = $leaveRequestService->submitEmployeeRequest(
            $this->getCurrentEmployeeId(),
            $startDate,
            $endDate,
            $leaveType,
            $reason
        );

        $this->addFlash($result['success'] ? 'success' : 'error', (string) $result['message']);
        return $this->redirectToRoute('app_employee_leave_requests');
    }

    private function getCurrentEmployeeId(): int
    {
        $user = $this->getUser();

        if (!$user instanceof DbUser || !$user->isEmployee()) {
            throw $this->createAccessDeniedException('Utilisateur employe invalide.');
        }

        return $user->getId();
    }
}
