<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Contract\ChangesetStorageInterface;
use App\AI\Domain\Enum\ChangesetStatus;
use App\AI\Domain\ValueObject\PendingChangeset;
use App\AI\Infrastructure\ToolCall;
use Doctrine\ORM\EntityManagerInterface;
use DateTimeImmutable;

final class ChangesetManager
{
    public function __construct(
        private readonly ChangesetStorageInterface $storage,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @param array<string, mixed> $result
     */
    public function stage(ToolCall $toolCall, array $result, object $user): PendingChangeset
    {
        $changeset = PendingChangeset::create(
            id: bin2hex(random_bytes(8)),
            sessionId: $result['sessionId'] ?? 'default',
            tool: $toolCall->name,
            action: $toolCall->arguments['action'] ?? 'update',
            payload: [
                'tool_args' => $toolCall->arguments,
                'result' => $result,
                'user_id' => method_exists($user, 'getId') ? $user->getId() : null,
            ],
        );

        $this->storage->persist($changeset);

        return $changeset;
    }

    public function confirm(string $changesetId, object $user): void
    {
        $changeset = $this->storage->find($changesetId);

        if ($changeset === null) {
            throw new \InvalidArgumentException("Changeset not found: {$changesetId}");
        }

        if ($changeset->status !== ChangesetStatus::PENDING) {
            throw new \InvalidArgumentException("Changeset is not pending");
        }

        $this->applyChange($changeset, $user);

        $confirmed = $changeset->confirm();
        $this->storage->persist($confirmed);
    }

    public function revert(string $changesetId): void
    {
        $changeset = $this->storage->find($changesetId);

        if ($changeset === null) {
            throw new \InvalidArgumentException("Changeset not found: {$changesetId}");
        }

        if ($changeset->status !== ChangesetStatus::PENDING) {
            throw new \InvalidArgumentException("Cannot revert non-pending changeset");
        }

        $reverted = $changeset->revert();
        $this->storage->persist($reverted);
    }

    /**
     * @return PendingChangeset[]
     */
    public function getPending(string $sessionId): array
    {
        return \array_filter(
            $this->storage->findBySession($sessionId),
            fn(PendingChangeset $c) => $c->status === ChangesetStatus::PENDING,
        );
    }

    public function stageFromChangeset(PendingChangeset $changeset): void
    {
        $this->storage->persist($changeset);
    }

    private function applyChange(PendingChangeset $changeset, object $user): void
    {
        $tool = $changeset->tool;
        $action = $changeset->action;
        $payload = $changeset->payload;

        $args = $payload['tool_args'] ?? [];

        try {
            match ($tool) {
                'manage_applications' => $this->applyApplicationChange($action, $args),
                'manage_interviews' => $this->applyInterviewChange($action, $args),
                'manage_job_offers' => $this->applyJobOfferChange($action, $args),
                default => null,
            };
        } catch (\Throwable $e) {
            throw new \RuntimeException("Failed to apply changeset: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    private function applyApplicationChange(string $action, array $args): void
    {
        if ($action === 'update_status' || $action === 'move') {
            $applicationId = $args['application_id'] ?? $args['id'] ?? null;
            $newStatus = $args['new_status'] ?? $args['status'] ?? null;

            if ($applicationId !== null && $newStatus !== null) {
                $app = $this->em->getRepository(\App\Entity\Recrutement\Application::class)
                    ->find($applicationId);

                if ($app !== null && !$app->isDeleted()) {
                    $app->setStatus($newStatus);
                    $this->em->flush();
                }
            }
        }

        if ($action === 'delete') {
            $ids = $args['filter_ids'] ?? ($args['ids'] ?? []);
            if (!empty($ids)) {
                $qb = $this->em->createQueryBuilder()
                    ->update(\App\Entity\Recrutement\Application::class, 'a')
                    ->set('a.isDeleted', ':deleted')
                    ->where('a.id IN (:ids)')
                    ->setParameter('deleted', true)
                    ->setParameter('ids', $ids);

                $qb->getQuery()->execute();
            }
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    private function applyInterviewChange(string $action, array $args): void
    {
        if ($action === 'cancel' || $action === 'delete') {
            $id = $args['id'] ?? $args['interview_id'] ?? null;
            if ($id !== null) {
                $interview = $this->em->getRepository(\App\Entity\Recrutement\Interview::class)
                    ->find($id);
                if ($interview !== null) {
                    $interview->setIsDeleted(true);
                    $this->em->flush();
                }
            }
        }

        if ($action === 'update') {
            $id = $args['id'] ?? null;
            if ($id !== null) {
                $interview = $this->em->getRepository(\App\Entity\Recrutement\Interview::class)
                    ->find($id);
                if ($interview !== null) {
                    if (isset($args['result'])) {
                        $interview->setResult($args['result']);
                    }
                    if (isset($args['feedback'])) {
                        $interview->setFeedback($args['feedback']);
                    }
                    if (isset($args['score'])) {
                        $interview->setScore((int) $args['score']);
                    }
                    $this->em->flush();
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    private function applyJobOfferChange(string $action, array $args): void
    {
        if ($action === 'change_status') {
            $id = $args['id'] ?? $args['job_offer_id'] ?? null;
            $newStatus = $args['new_status'] ?? null;

            if ($id !== null && $newStatus !== null) {
                $offer = $this->em->getRepository(\App\Entity\Recrutement\JobOffer::class)
                    ->find($id);
                if ($offer !== null) {
                    $offer->setStatus($newStatus);
                    $this->em->flush();
                }
            }
        }

        if ($action === 'update') {
            $id = $args['id'] ?? null;
            if ($id !== null) {
                $offer = $this->em->getRepository(\App\Entity\Recrutement\JobOffer::class)
                    ->find($id);
                if ($offer !== null) {
                    if (isset($args['title'])) {
                        $offer->setTitle($args['title']);
                    }
                    if (isset($args['description'])) {
                        $offer->setDescription($args['description']);
                    }
                    if (isset($args['location'])) {
                        $offer->setLocation($args['location']);
                    }
                    $this->em->flush();
                }
            }
        }

        if ($action === 'delete') {
            $id = $args['id'] ?? $args['job_offer_id'] ?? null;
            if ($id !== null) {
                $offer = $this->em->getRepository(\App\Entity\Recrutement\JobOffer::class)
                    ->find($id);
                if ($offer !== null) {
                    $offer->setIsDeleted(true);
                    $this->em->flush();
                }
            }
        }
    }
}
