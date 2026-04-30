<?php

namespace App\Repository\Relation;

use App\Entity\Relation\RequestType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RequestType>
 */
class RequestTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RequestType::class);
    }

    public function fetchAll(): array
    {
        return $this->getConnection()->fetchAllAssociative(
            'SELECT * FROM request_types ORDER BY name'
        );
    }

    public function fetchById(int $id): ?array
    {
        $row = $this->getConnection()->fetchAssociative(
            'SELECT * FROM request_types WHERE id = ?',
            [$id]
        );

        return $row ?: null;
    }

    public function insert(array $data): void
    {
        $this->getConnection()->insert('request_types', $data);
    }

    public function updateRequestType(int $id, array $data): void
    {
        $this->getConnection()->update('request_types', $data, ['id' => $id]);
    }

    public function deleteRequestType(int $id): void
    {
        $this->getConnection()->delete('request_types', ['id' => $id]);
    }

    private function getConnection(): Connection
    {
        return $this->getEntityManager()->getConnection();
    }
}

