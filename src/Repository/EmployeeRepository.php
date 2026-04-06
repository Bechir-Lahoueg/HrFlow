<?php

namespace App\Repository;

use App\Entity\Employee;
use App\Security\DbUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Employee>
 */
class EmployeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employee::class);
    }

    /**
     * @return Employee[] Returns all employees managed by an RH
     */
    public function findByRh(DbUser $rh): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.rhId = :rhId')
            ->setParameter('rhId', $rh->getId())
            ->orderBy('e.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByRh(int $id, DbUser $rh): ?Employee
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.id = :id')
            ->andWhere('e.rhId = :rhId')
            ->setParameter('id', $id)
            ->setParameter('rhId', $rh->getId())
            ->getQuery()
            ->getOneOrNullResult();
    }
}
