<?php

declare(strict_types=1);

namespace App\Repository\AI;

use App\AI\Contract\ChangesetStorageInterface;
use App\AI\Domain\Enum\ChangesetStatus;
use App\AI\Domain\ValueObject\PendingChangeset;
use App\Entity\AI\PendingChangesetEntity;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;

class PendingChangesetRepository implements ChangesetStorageInterface
{
    private EntityManagerInterface $em;

    public function __construct(ManagerRegistry $registry)
    {
        $manager = $registry->getManager();
        assert($manager instanceof EntityManagerInterface);
        $this->em = $manager;
    }

    public function persist(PendingChangeset $changeset): void
    {
        $existing = $this->em->find(PendingChangesetEntity::class, $changeset->id);
        
        if ($existing !== null) {
            $existing->setStatus($changeset->status->value);
            $existing->setConfirmedAt($changeset->confirmedAt);
        } else {
            $entity = new PendingChangesetEntity();
            $entity->setId($changeset->id);
            $entity->setSessionId($changeset->sessionId);
            $entity->setTool($changeset->tool);
            $entity->setAction($changeset->action);
            $entity->setPayload($changeset->payload);
            $entity->setStatus($changeset->status->value);
            $entity->setCreatedAt($changeset->createdAt);
            $entity->setConfirmedAt($changeset->confirmedAt);
            $this->em->persist($entity);
        }
        
        $this->em->flush();
    }

    public function find(string $id): ?PendingChangeset
    {
        $entity = $this->em->find(PendingChangesetEntity::class, $id);
        if ($entity === null) {
            return null;
        }
        return $this->toValueObject($entity);
    }

    /** @return array<PendingChangeset> */
    public function findBySession(string $sessionId): array
    {
        $entities = $this->em->createQueryBuilder()
            ->select('c')
            ->from(PendingChangesetEntity::class, 'c')
            ->where('c.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->getQuery()
            ->getResult();

        return \array_map(
            fn(PendingChangesetEntity $e) => $this->toValueObject($e),
            $entities,
        );
    }

    private function toValueObject(PendingChangesetEntity $entity): PendingChangeset
    {
        return new PendingChangeset(
            id: $entity->getId() ?? '',
            sessionId: $entity->getSessionId(),
            tool: $entity->getTool(),
            action: $entity->getAction(),
            payload: $entity->getPayload(),
            status: ChangesetStatus::from($entity->getStatus()),
            createdAt: $entity->getCreatedAt(),
            confirmedAt: $entity->getConfirmedAt(),
        );
    }
}