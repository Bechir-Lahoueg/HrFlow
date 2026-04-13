<?php

namespace App\Repository\Formation;

use App\Entity\Formation\EmployeeNotification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmployeeNotification>
 */
class EmployeeNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmployeeNotification::class);
    }

    /** @return EmployeeNotification[] */
    public function findUnreadByEmployee(int $employeeId, int $limit = 8): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.employee = :employeeId')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('employeeId', $employeeId)
            ->setParameter('isRead', false)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUnreadByEmployee(int $employeeId): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.employee = :employeeId')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('employeeId', $employeeId)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markAllAsReadByEmployee(int $employeeId): void
    {
        $this->getEntityManager()->createQueryBuilder()
            ->update(EmployeeNotification::class, 'n')
            ->set('n.isRead', ':isRead')
            ->where('n.employee = :employeeId')
            ->andWhere('n.isRead = :currentState')
            ->setParameter('isRead', true)
            ->setParameter('currentState', false)
            ->setParameter('employeeId', $employeeId)
            ->getQuery()
            ->execute();
    }

    public function findOneByIdAndEmployee(int $notificationId, int $employeeId): ?EmployeeNotification
    {
        return $this->createQueryBuilder('n')
            ->where('n.id = :notificationId')
            ->andWhere('n.employee = :employeeId')
            ->setParameter('notificationId', $notificationId)
            ->setParameter('employeeId', $employeeId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function markAsRead(EmployeeNotification $notification): void
    {
        if ($notification->isRead()) {
            return;
        }

        $notification->setIsRead(true);
        $this->getEntityManager()->flush();
    }

    /** @return EmployeeNotification[] */
    public function findAllByEmployee(int $employeeId, int $limit = 100): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.employee = :employeeId')
            ->setParameter('employeeId', $employeeId)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

