<?php

namespace App\Twig;

use App\Repository\Formation\EmployeeNotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class EmployeeNotificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly EmployeeNotificationRepository $notificationRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('employee_unread_notifications_count', [$this, 'getUnreadCount']),
            new TwigFunction('employee_unread_notifications', [$this, 'getUnreadNotifications']),
        ];
    }

    public function getUnreadCount(): int
    {
        $user = $this->security->getUser();
        if (!$user || !method_exists($user, 'isEmployee') || !$user->isEmployee()) {
            return 0;
        }

        try {
            return $this->notificationRepository->countUnreadByEmployee($user->getId());
        } catch (\Throwable) {
            // La migration peut ne pas etre appliquee en local: on masque les notifications.
            return 0;
        }
    }

    public function getUnreadNotifications(int $limit = 6): array
    {
        $user = $this->security->getUser();
        if (!$user || !method_exists($user, 'isEmployee') || !$user->isEmployee()) {
            return [];
        }

        try {
            return $this->notificationRepository->findUnreadByEmployee($user->getId(), $limit);
        } catch (\Throwable) {
            // La migration peut ne pas etre appliquee en local: on masque les notifications.
            return [];
        }
    }
}


