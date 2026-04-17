<?php

namespace App\Controller;

use App\Entity\Rh\LeaveNotification;
use App\Repository\Rh\LeaveNotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/notifications/leave')]
final class LeaveNotificationController extends AbstractController
{
    public function __construct(
        private readonly LeaveNotificationRepository $repository,
    ) {
    }

    /**
     * JSON API for polling — returns unread count + latest notifications.
     */
    #[Route('/poll', name: 'leave_notifications_poll', methods: ['GET'])]
    public function poll(): JsonResponse
    {
        [$recipientType, $recipientId] = $this->resolveRecipient();
        if ($recipientId === null) {
            return $this->json(['count' => 0, 'notifications' => []]);
        }

        $count = $this->repository->countUnreadByRecipient($recipientType, $recipientId);
        $notifications = $this->repository->findUnreadByRecipient($recipientType, $recipientId, 6);

        $items = [];
        foreach ($notifications as $n) {
            $items[] = [
                'id' => $n->getId(),
                'title' => $n->getTitle(),
                'message' => $n->getMessage(),
                'type' => $n->getType(),
                'leaveRequestId' => $n->getLeaveRequestId(),
                'createdAt' => $n->getCreatedAt()?->format('d/m/Y H:i'),
            ];
        }

        return $this->json(['count' => $count, 'notifications' => $items]);
    }

    /**
     * Mark all leave notifications as read for current user.
     */
    #[Route('/mark-all-read', name: 'leave_notifications_mark_all_read', methods: ['POST'])]
    public function markAllRead(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('leave-notifications-mark-all-read', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        [$recipientType, $recipientId] = $this->resolveRecipient();
        if ($recipientId !== null) {
            $this->repository->markAllAsReadByRecipient($recipientType, $recipientId);
        }

        $referer = $request->headers->get('referer');
        return $this->redirect($referer ?: '/');
    }

    /**
     * Open/read a specific notification and redirect to leave page.
     */
    #[Route('/{id}/open', name: 'leave_notification_open', methods: ['GET'])]
    public function open(int $id): Response
    {
        [$recipientType, $recipientId] = $this->resolveRecipient();
        if ($recipientId === null) {
            throw $this->createAccessDeniedException();
        }

        $notification = $this->repository->findOneByIdAndRecipient($id, $recipientType, $recipientId);
        if (!$notification) {
            throw $this->createNotFoundException();
        }

        if (!$notification->isRead()) {
            $notification->setIsRead(true);
            $this->repository->getEntityManager()->flush();
        }

        $type = $notification->getType();
        if (str_starts_with($type, 'formation_') || str_starts_with($type, 'session_')) {
            $user = $this->getUser();
            if (method_exists($user, 'isEmployee') && $user->isEmployee()) {
                return $this->redirectToRoute('employee_formation_index');
            }

            return $this->redirectToRoute('rh_formation_list');
        }

        // Redirect to the appropriate leave page based on role
        $user = $this->getUser();
        if (method_exists($user, 'isEmployee') && $user->isEmployee()) {
            return $this->redirectToRoute('app_employee_leave_requests');
        }

        $role = method_exists($user, 'getRole') ? $user->getRole() : '';
        if (strtoupper($role) === 'ADMIN') {
            return $this->redirectToRoute('app_admin_leave_exceptions');
        }

        return $this->redirectToRoute('app_rh_leave_requests');
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function resolveRecipient(): array
    {
        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getId')) {
            return ['', null];
        }

        if (method_exists($user, 'isEmployee') && $user->isEmployee()) {
            return [LeaveNotification::RECIPIENT_EMPLOYEE, $user->getId()];
        }

        return [LeaveNotification::RECIPIENT_USER, $user->getId()];
    }
}
