<?php

namespace App\Repository\Rh;

use App\Entity\Rh\LeaveNotification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LeaveNotification>
 */
class LeaveNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeaveNotification::class);
    }

    /** @return LeaveNotification[] */
    public function findUnreadByRecipient(string $recipientType, int $recipientId, int $limit = 8): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.recipientType = :type')
            ->andWhere('n.recipientId = :id')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('type', $recipientType)
            ->setParameter('id', $recipientId)
            ->setParameter('isRead', false)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUnreadByRecipient(string $recipientType, int $recipientId): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.recipientType = :type')
            ->andWhere('n.recipientId = :id')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('type', $recipientType)
            ->setParameter('id', $recipientId)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markAllAsReadByRecipient(string $recipientType, int $recipientId): void
    {
        $this->getEntityManager()->createQueryBuilder()
            ->update(LeaveNotification::class, 'n')
            ->set('n.isRead', ':isRead')
            ->where('n.recipientType = :type')
            ->andWhere('n.recipientId = :id')
            ->andWhere('n.isRead = :currentState')
            ->setParameter('isRead', true)
            ->setParameter('type', $recipientType)
            ->setParameter('id', $recipientId)
            ->setParameter('currentState', false)
            ->getQuery()
            ->execute();
    }

    public function findOneByIdAndRecipient(int $notificationId, string $recipientType, int $recipientId): ?LeaveNotification
    {
        return $this->createQueryBuilder('n')
            ->where('n.id = :notificationId')
            ->andWhere('n.recipientType = :type')
            ->andWhere('n.recipientId = :id')
            ->setParameter('notificationId', $notificationId)
            ->setParameter('type', $recipientType)
            ->setParameter('id', $recipientId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return LeaveNotification[] */
    public function findAllByRecipient(string $recipientType, int $recipientId, int $limit = 100): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.recipientType = :type')
            ->andWhere('n.recipientId = :id')
            ->setParameter('type', $recipientType)
            ->setParameter('id', $recipientId)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
