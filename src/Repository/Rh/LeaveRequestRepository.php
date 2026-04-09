<?php

namespace App\Repository\Rh;

use App\Entity\Rh\LeaveRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LeaveRequest>
 */
class LeaveRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeaveRequest::class);
    }

    /** @return LeaveRequest[] */
    public function findByEmployee(int $employeeId): array
    {
        return $this->createQueryBuilder('lr')
            ->where('lr.employee = :employeeId')
            ->setParameter('employeeId', $employeeId)
            ->orderBy('lr.requestDate', 'DESC')
            ->addOrderBy('lr.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countPendingByEmployee(int $employeeId): int
    {
        return (int) $this->createQueryBuilder('lr')
            ->select('COUNT(lr.id)')
            ->where('lr.employee = :employeeId')
            ->andWhere('lr.status = :status')
            ->setParameter('employeeId', $employeeId)
            ->setParameter('status', 'ATTENTE')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPendingByRh(int $rhId): int
    {
        return (int) $this->createQueryBuilder('lr')
            ->select('COUNT(lr.id)')
            ->join('lr.employee', 'e')
            ->where('e.rhId = :rhId')
            ->andWhere('lr.status = :status')
            ->setParameter('rhId', $rhId)
            ->setParameter('status', 'ATTENTE')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasDateOverlap(int $employeeId, \DateTimeInterface $startDate, \DateTimeInterface $endDate): bool
    {
        return (int) $this->createQueryBuilder('lr')
            ->select('COUNT(lr.id)')
            ->where('lr.employee = :employeeId')
            ->andWhere('lr.status IN (:statuses)')
            ->andWhere('NOT (lr.endDate < :startDate OR lr.startDate > :endDate)')
            ->setParameter('employeeId', $employeeId)
            ->setParameter('statuses', ['ATTENTE', 'ACCEPTE'])
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /** @return LeaveRequest[] */
    public function findByRh(
        int $rhId,
        ?string $statusFilter,
        string $employeeSearch = '',
        string $leaveTypeSearch = '',
        string $search = '',
        string $sort = 'request_date',
        string $direction = 'DESC'
    ): array
    {
        $sortMap = [
            'request_date' => 'lr.requestDate',
            'start_date' => 'lr.startDate',
            'days' => 'lr.daysCount',
            'employee' => 'lr.employeeName',
            'status' => 'lr.status',
        ];

        $orderByField = $sortMap[$sort] ?? 'lr.requestDate';
        $orderDirection = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('lr')
            ->join('lr.employee', 'e')
            ->where('e.rhId = :rhId')
            ->setParameter('rhId', $rhId)
            ->orderBy($orderByField, $orderDirection)
            ->addOrderBy('lr.id', 'DESC');

        if ($statusFilter !== null && in_array($statusFilter, ['ATTENTE', 'ACCEPTE', 'REFUSE'], true)) {
            $qb->andWhere('lr.status = :status')
               ->setParameter('status', $statusFilter);
        }

        if (trim($employeeSearch) !== '') {
            $qb->andWhere('lr.employeeName LIKE :empSearch')
               ->setParameter('empSearch', '%' . trim($employeeSearch) . '%');
        }

        if (trim($leaveTypeSearch) !== '') {
            $qb->andWhere('lr.leaveType LIKE :typeSearch')
               ->setParameter('typeSearch', '%' . trim($leaveTypeSearch) . '%');
        }

        if (trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $qb->andWhere('(
                lr.employeeName LIKE :globalSearch
                OR lr.leaveType LIKE :globalSearch
                OR lr.reason LIKE :globalSearch
                OR lr.status LIKE :globalSearch
            )')
            ->setParameter('globalSearch', $term);
        }

        return $qb->getQuery()->getResult();
    }

    public function getStatsByRh(int $rhId): array
    {
        $result = $this->createQueryBuilder('lr')
            ->select(
                'COUNT(lr.id) AS total_count',
                "SUM(CASE WHEN lr.status = 'ATTENTE' THEN 1 ELSE 0 END) AS pending_count",
                "SUM(CASE WHEN lr.status = 'ACCEPTE' THEN 1 ELSE 0 END) AS approved_count",
                "SUM(CASE WHEN lr.status = 'REFUSE' THEN 1 ELSE 0 END) AS rejected_count"
            )
            ->join('lr.employee', 'e')
            ->where('e.rhId = :rhId')
            ->setParameter('rhId', $rhId)
            ->getQuery()
            ->getSingleResult();

        return [
            'total_count' => (int) ($result['total_count'] ?? 0),
            'pending_count' => (int) ($result['pending_count'] ?? 0),
            'approved_count' => (int) ($result['approved_count'] ?? 0),
            'rejected_count' => (int) ($result['rejected_count'] ?? 0),
        ];
    }

    public function getStatsByEmployee(int $employeeId): array
    {
        $result = $this->createQueryBuilder('lr')
            ->select(
                "SUM(CASE WHEN lr.status = 'ATTENTE' THEN 1 ELSE 0 END) AS pending_count",
                "SUM(CASE WHEN lr.status = 'ACCEPTE' THEN 1 ELSE 0 END) AS approved_count",
                "SUM(CASE WHEN lr.status = 'REFUSE' THEN 1 ELSE 0 END) AS rejected_count"
            )
            ->where('lr.employee = :employeeId')
            ->setParameter('employeeId', $employeeId)
            ->getQuery()
            ->getSingleResult();

        return [
            'pending_count' => (int) ($result['pending_count'] ?? 0),
            'approved_count' => (int) ($result['approved_count'] ?? 0),
            'rejected_count' => (int) ($result['rejected_count'] ?? 0),
        ];
    }

    public function findOnePendingByEmployee(int $leaveRequestId, int $employeeId): ?LeaveRequest
    {
        return $this->createQueryBuilder('lr')
            ->where('lr.id = :id')
            ->andWhere('lr.employee = :employeeId')
            ->andWhere('lr.status = :status')
            ->setParameter('id', $leaveRequestId)
            ->setParameter('employeeId', $employeeId)
            ->setParameter('status', 'ATTENTE')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByRh(int $leaveRequestId, int $rhId): ?LeaveRequest
    {
        return $this->createQueryBuilder('lr')
            ->join('lr.employee', 'e')
            ->where('lr.id = :id')
            ->andWhere('e.rhId = :rhId')
            ->setParameter('id', $leaveRequestId)
            ->setParameter('rhId', $rhId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return LeaveRequest[] */
    public function findAdminExceptionPending(): array
    {
        return $this->createQueryBuilder('lr')
            ->join('lr.employee', 'e')
            ->where('lr.requestCategory = :category')
            ->andWhere('lr.workflowStatus = :workflowStatus')
            ->setParameter('category', 'EXCEPTION')
            ->setParameter('workflowStatus', 'ADMIN_PENDING')
            ->orderBy('lr.requestDate', 'DESC')
            ->addOrderBy('lr.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return LeaveRequest[] */
    public function findExpiredExceptionalPending(\DateTimeInterface $today): array
    {
        return $this->createQueryBuilder('lr')
            ->where('lr.requestCategory = :category')
            ->andWhere('lr.status = :status')
            ->andWhere('lr.workflowStatus IN (:workflows)')
            ->andWhere('lr.startDate <= :today')
            ->setParameter('category', 'EXCEPTION')
            ->setParameter('status', 'ATTENTE')
            ->setParameter('workflows', ['RH_PENDING', 'ADMIN_PENDING'])
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();
    }
}
