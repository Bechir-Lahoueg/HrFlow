<?php

namespace App\Controller\admin;

use App\Service\LeaveRequestService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminLeaveController extends AbstractController
{
    #[Route('/welcome/admin/leaves/exceptions', name: 'app_admin_leave_exceptions', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(LeaveRequestService $leaveRequestService): Response
    {
        return $this->render('DashboardAdmin/leave_exceptions.html.twig', [
            'user' => $this->getUser(),
            'leaveRequests' => $leaveRequestService->getAdminExceptionRequests(),
        ]);
    }

    #[Route('/welcome/admin/leaves/exceptions/{id}/approve', name: 'app_admin_leave_exception_approve', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function approve(string $id, Request $request, LeaveRequestService $leaveRequestService): RedirectResponse
    {
        $idInt = (int) $id;
        if (!$this->isCsrfTokenValid('admin_leave_exception_approve_' . $idInt, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_leave_exceptions');
        }

        $comment = trim((string) $request->request->get('admin_comment', ''));
        $result = $leaveRequestService->approveExceptionByAdmin($idInt, $comment, (string) $this->getUser()?->getUserIdentifier());
        $this->addFlash($result['success'] ? 'success' : 'error', (string) $result['message']);

        return $this->redirectToRoute('app_admin_leave_exceptions');
    }

    #[Route('/welcome/admin/leaves/exceptions/{id}/reject', name: 'app_admin_leave_exception_reject', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reject(string $id, Request $request, LeaveRequestService $leaveRequestService): RedirectResponse
    {
        $idInt = (int) $id;
        if (!$this->isCsrfTokenValid('admin_leave_exception_reject_' . $idInt, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_leave_exceptions');
        }

        $comment = trim((string) $request->request->get('admin_comment', ''));
        $result = $leaveRequestService->rejectExceptionByAdmin($idInt, $comment, (string) $this->getUser()?->getUserIdentifier());
        $this->addFlash($result['success'] ? 'success' : 'error', (string) $result['message']);

        return $this->redirectToRoute('app_admin_leave_exceptions');
    }
}
