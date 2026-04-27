<?php

namespace App\Controller\admin;

use App\Repository\Rh\LeaveRequestRepository;
use App\Service\Rh\LeaveRequestService;
use App\Service\Shared\AiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $adminActor = (string) $this->getUser()?->getUserIdentifier();

        return $this->render('DashboardAdmin/leave_exceptions.html.twig', [
            'user' => $this->getUser(),
            'leaveRequests' => $leaveRequestService->getAdminExceptionRequests($adminActor),
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

    #[Route('/welcome/admin/leaves/exceptions/ai/comment', name: 'app_admin_leave_exception_ai_comment', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function aiComment(
        Request $request,
        LeaveRequestRepository $leaveRequestRepository,
        AiService $aiService,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['success' => false, 'message' => 'Payload JSON invalide.'], 400);
        }

        if (!$this->isCsrfTokenValid('admin_leave_exception_ai_comment', (string) ($payload['_token'] ?? ''))) {
            return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide.'], 403);
        }

        $leaveId = (int) ($payload['leave_id'] ?? 0);
        if ($leaveId <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'Identifiant de demande invalide.'], 422);
        }

        $leave = $leaveRequestRepository->find($leaveId);
        if ($leave === null || $leave->getRequestCategory() !== LeaveRequestService::CATEGORY_EXCEPTION) {
            return new JsonResponse(['success' => false, 'message' => 'Demande exceptionnelle introuvable.'], 404);
        }

        if ($leave->getWorkflowStatus() !== LeaveRequestService::WORKFLOW_ADMIN_PENDING) {
            return new JsonResponse(['success' => false, 'message' => 'La demande n\'est pas dans l\'etape Admin en attente.'], 422);
        }

        $action = strtoupper(trim((string) ($payload['action'] ?? 'APPROVE')));
        if (!in_array($action, ['APPROVE', 'REJECT'], true)) {
            return new JsonResponse(['success' => false, 'message' => 'Action invalide.'], 422);
        }

        $suggestion = $aiService->generateAdminLeaveDecisionComment([
            'action' => $action,
            'employee_name' => (string) $leave->getEmployeeName(),
            'leave_type' => (string) $leave->getLeaveType(),
            'start_date' => $leave->getStartDate()?->format('Y-m-d') ?? '',
            'end_date' => $leave->getEndDate()?->format('Y-m-d') ?? '',
            'days_count' => $leave->getDaysCount(),
            'urgency_level' => (string) $leave->getUrgencyLevel(),
            'reason' => (string) $leave->getReason(),
            'rh_comment' => (string) $leave->getRhComment(),
        ]);

        return new JsonResponse([
            'success' => true,
            'suggestion' => $suggestion,
            'notice' => 'Texte suggere par IA, a verifier avant envoi.',
        ]);
    }
}
