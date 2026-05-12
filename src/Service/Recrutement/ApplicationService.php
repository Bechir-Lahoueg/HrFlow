<?php

declare(strict_types=1);

namespace App\Service\Recrutement;

use App\AI\Domain\DTO\ApplicationQuery;
use App\AI\Domain\ValueObject\PendingChangeset;
use Doctrine\ORM\EntityManagerInterface;

final class ApplicationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ?\App\AI\Core\ChangesetManager $changesetManager = null,
    ) {}

    public function list(ApplicationQuery $query): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->where('a.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->orderBy('a.appliedAt', 'DESC');

        $this->applyFilters($qb, $query);

        if ($query->limit !== null) {
            $qb->setMaxResults($query->limit);
        }

        $applications = $qb->getQuery()->getResult();

        $data = [];
        foreach ($applications as $app) {
            $data[] = [
                'id' => $app->getId(),
                'candidate_name' => $app->getCandidateName(),
                'email' => $app->getEmailAddress(),
                'job_title' => $app->getJobOffer()?->getTitle(),
                'status' => $app->getStatus(),
                'status_label' => $app->getStatusLabel(),
                'applied_at' => $app->getAppliedAt()?->format('Y-m-d H:i:s'),
                'department' => $app->getDepartment(),
            ];
        }

        return $data;
    }

    public function updateStatus(int $applicationId, string $newStatus, object $user): ?PendingChangeset
    {
        $app = $this->em->getRepository(\App\Entity\Recrutement\Application::class)->find($applicationId);
        if ($app === null || $app->isDeleted()) {
            return null;
        }

        if ($this->changesetManager !== null) {
            $changeset = PendingChangeset::create(
                id: bin2hex(random_bytes(8)),
                sessionId: 'default',
                tool: 'manage_applications',
                action: 'update_status',
                payload: [
                    'application_id' => $applicationId,
                    'old_status' => $app->getStatus(),
                    'new_status' => $newStatus,
                    'user_id' => method_exists($user, 'getId') ? $user->getId() : null,
                ],
            );
            $this->changesetManager->stageFromChangeset($changeset);
            return $changeset;
        }

        $app->setStatus($newStatus);
        $this->em->flush();

        return null;
    }

    public function delete(array $ids, object $user): ?PendingChangeset
    {
        if ($this->changesetManager !== null) {
            $changeset = PendingChangeset::create(
                id: bin2hex(random_bytes(8)),
                sessionId: 'default',
                tool: 'manage_applications',
                action: 'delete',
                payload: [
                    'filter_ids' => $ids,
                    'user_id' => method_exists($user, 'getId') ? $user->getId() : null,
                ],
            );
            $this->changesetManager->stageFromChangeset($changeset);
            return $changeset;
        }

        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->where('a.id IN (:ids)')
            ->setParameter('ids', $ids);

        foreach ($qb->getQuery()->getResult() as $app) {
            $app->setIsDeleted(true);
        }
        $this->em->flush();

        return null;
    }

    private function applyFilters($qb, ApplicationQuery $query): void
    {
        if ($query->jobOfferId !== null) {
            $qb->andWhere('a.jobOffer = :jobId')
                ->setParameter('jobId', $query->jobOfferId);
        }

        if ($query->status !== null) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $query->status);
        }

        if ($query->department !== null) {
            $qb->andWhere('a.department = :dept')
                ->setParameter('dept', $query->department);
        }

        if (!empty($query->ids)) {
            $qb->andWhere('a.id IN (:ids)')
                ->setParameter('ids', $query->ids);
        }
    }
}
