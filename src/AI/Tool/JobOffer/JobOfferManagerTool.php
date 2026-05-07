<?php

declare(strict_types=1);

namespace App\AI\Tool\JobOffer;

use App\AI\Domain\DTO\JobOfferPayload;
use App\AI\Tool\AbstractEntityManagerTool;
use App\AI\Domain\ValueObject\PendingChangeset;
use App\AI\Domain\ValueObject\ToolOutput;

final class JobOfferManagerTool extends AbstractEntityManagerTool
{
    protected function getDtoClass(): string
    {
        return JobOfferPayload::class;
    }

    protected function getToolName(): string
    {
        return 'manage_job_offers';
    }

    protected function getToolDescription(): string
    {
        return 'Gère les offres d\'emploi : consultation (liste, recherche, détail), création, modification, changement de statut et suppression.';
    }

    protected function handle(object $dto, object $user): ToolOutput
    {
        \assert($dto instanceof JobOfferPayload);

        return match ($dto->action) {
            'list' => $this->handleList($dto, $user),
            'search' => $this->handleSearch($dto, $user),
            'view' => $this->handleView($dto),
            'create' => $this->handleCreate($dto, $user),
            'update' => $this->handleUpdate($dto, $user),
            'change_status' => $this->handleChangeStatus($dto, $user),
            'delete' => $this->handleDelete($dto, $user),
            default => $this->validationError("Action '{$dto->action}' non reconnue."),
        };
    }

    private function handleList(JobOfferPayload $dto, object $user): ToolOutput
    {
        $qb = $this->em->createQueryBuilder()
            ->select('j')
            ->from(\App\Entity\Recrutement\JobOffer::class, 'j')
            ->where('j.isDeleted = :deleted')
            ->andWhere('j.createdBy = :userId')
            ->setParameter('deleted', false)
            ->setParameter('userId', $this->getUserId($user))
            ->orderBy('j.createdAt', 'DESC');

        if ($dto->status !== null) {
            $qb->andWhere('j.status = :status')
                ->setParameter('status', $dto->status);
        }

        if ($dto->department !== null) {
            $qb->andWhere('j.department = :dept')
                ->setParameter('dept', $dto->department);
        }

        $qb->setMaxResults($dto->limit ?? 50);

        $offers = $qb->getQuery()->getResult();

        $data = [];
        foreach ($offers as $offer) {
            $data[] = [
                'id' => $offer->getId(),
                'title' => $offer->getTitle(),
                'department' => $offer->getDepartment(),
                'location' => $offer->getLocation(),
                'status' => $offer->getStatus(),
                'employment_type' => $offer->getEmploymentType(),
                'created_at' => $offer->getCreatedAt()?->format('Y-m-d'),
            ];
        }

        $summary = \count($data) . ' offre(s) d\'emploi trouvée(s).';
        if (\count($data) > 0) {
            $summary .= "\nListe des offres:\n";
            foreach ($data as $offer) {
                $summary .= \sprintf(
                    "- ID: %d | Titre: %s | Département: %s | Lieu: %s | Statut: %s | Type: %s | Créée le: %s\n",
                    $offer['id'],
                    $offer['title'],
                    $offer['department'] ?? 'N/A',
                    $offer['location'] ?? 'N/A',
                    $offer['status'] ?? 'N/A',
                    $offer['employment_type'] ?? 'N/A',
                    $offer['created_at'] ?? 'N/A',
                );
            }
        }

        return $this->createOutput($summary, [
            'type' => 'job_offers_list',
            'data' => $data,
        ]);
    }

    private function handleSearch(JobOfferPayload $dto, object $user): ToolOutput
    {
        $qb = $this->em->createQueryBuilder()
            ->select('j')
            ->from(\App\Entity\Recrutement\JobOffer::class, 'j')
            ->where('j.isDeleted = :deleted')
            ->andWhere('j.createdBy = :userId')
            ->setParameter('deleted', false)
            ->setParameter('userId', $this->getUserId($user));

        if ($dto->search !== null) {
            $qb->andWhere('(j.title LIKE :search OR j.description LIKE :search)')
                ->setParameter('search', '%' . $dto->search . '%');
        }

        if ($dto->status !== null) {
            $qb->andWhere('j.status = :status')
                ->setParameter('status', $dto->status);
        }

        if ($dto->department !== null) {
            $qb->andWhere('j.department = :dept')
                ->setParameter('dept', $dto->department);
        }

        $qb->setMaxResults($dto->limit ?? 50)
            ->orderBy('j.createdAt', 'DESC');

        $offers = $qb->getQuery()->getResult();

        $data = [];
        foreach ($offers as $offer) {
            $data[] = [
                'id' => $offer->getId(),
                'title' => $offer->getTitle(),
                'department' => $offer->getDepartment(),
                'location' => $offer->getLocation(),
                'status' => $offer->getStatus(),
                'employment_type' => $offer->getEmploymentType(),
                'created_at' => $offer->getCreatedAt()?->format('Y-m-d'),
            ];
        }

        $summary = \count($data) . ' offre(s) trouvée(s) pour la recherche.';
        foreach ($data as $offer) {
            $summary .= \sprintf(
                "\n- ID: %d | Titre: %s | Statut: %s",
                $offer['id'],
                $offer['title'],
                $offer['status'] ?? 'N/A',
            );
        }

        return $this->createOutput($summary, [
            'type' => 'job_offers_list',
            'data' => $data,
        ]);
    }

    private function handleView(JobOfferPayload $dto): ToolOutput
    {
        if ($dto->id === null) {
            return $this->validationError("L'ID de l'offre est requis pour l'action 'view'.");
        }

        $offer = $this->em->find(\App\Entity\Recrutement\JobOffer::class, $dto->id);

        if ($offer === null || $offer->isDeleted()) {
            return $this->createOutput("Offre d'emploi #{$dto->id} introuvable.");
        }

        $data = [
            'id' => $offer->getId(),
            'title' => $offer->getTitle(),
            'description' => $offer->getDescription(),
            'department' => $offer->getDepartment(),
            'location' => $offer->getLocation(),
            'status' => $offer->getStatus(),
            'employment_type' => $offer->getEmploymentType(),
            'salary_min' => $offer->getSalaryMin(),
            'salary_max' => $offer->getSalaryMax(),
            'created_at' => $offer->getCreatedAt()?->format('Y-m-d'),
        ];

        $summary = "Offre #{$data['id']}: {$data['title']}\n"
            . "Département: {$data['department']}\n"
            . "Lieu: {$data['location']}\n"
            . "Statut: {$data['status']}\n"
            . "Type: {$data['employment_type']}";

        return $this->createOutput($summary, [
            'type' => 'job_offer_card',
            'data' => $data,
        ]);
    }

    private function handleCreate(JobOfferPayload $dto, object $user): ToolOutput
    {
        $summary = "Création d'offre d'emploi demandée: {$dto->title}. Confirmation requise.";

        return $this->createOutput($summary, [
            'type' => 'job_offer_changeset',
            'action' => 'create',
            'payload' => [
                'title' => $dto->title,
                'description' => $dto->description,
                'department' => $dto->department,
                'location' => $dto->location,
                'employment_type' => $dto->employment_type,
                'salary_min' => $dto->salary_min,
                'salary_max' => $dto->salary_max,
                'status' => $dto->status ?? 'DRAFT',
            ],
        ], true);
    }

    private function handleUpdate(JobOfferPayload $dto, object $user): ToolOutput
    {
        if ($dto->id === null) {
            return $this->validationError("L'ID de l'offre est requis pour l'action 'update'.");
        }

        $offer = $this->em->find(\App\Entity\Recrutement\JobOffer::class, $dto->id);

        if ($offer === null || $offer->isDeleted()) {
            return $this->createOutput("Offre d'emploi #{$dto->id} introuvable.");
        }

        $changes = [];
        if ($dto->title !== null) { $changes['title'] = $dto->title; }
        if ($dto->description !== null) { $changes['description'] = $dto->description; }
        if ($dto->department !== null) { $changes['department'] = $dto->department; }
        if ($dto->location !== null) { $changes['location'] = $dto->location; }
        if ($dto->employment_type !== null) { $changes['employment_type'] = $dto->employment_type; }
        if ($dto->salary_min !== null) { $changes['salary_min'] = $dto->salary_min; }
        if ($dto->salary_max !== null) { $changes['salary_max'] = $dto->salary_max; }

        if (empty($changes)) {
            return $this->createOutput("Aucune modification détectée pour l'offre #{$dto->id}.");
        }

        $summary = "Modification de l'offre #{$dto->id} demandée. Confirmation requise.";

        return $this->createOutput($summary, [
            'type' => 'job_offer_changeset',
            'action' => 'update',
            'job_offer_id' => $dto->id,
            'payload' => $changes,
        ], true);
    }

    private function handleChangeStatus(JobOfferPayload $dto, object $user): ToolOutput
    {
        if ($dto->id === null || $dto->new_status === null) {
            return $this->validationError("L'ID et le nouveau statut sont requis pour l'action 'change_status'.");
        }

        $offer = $this->em->find(\App\Entity\Recrutement\JobOffer::class, $dto->id);

        if ($offer === null || $offer->isDeleted()) {
            return $this->createOutput("Offre d'emploi #{$dto->id} introuvable.");
        }

        $oldStatus = $offer->getStatus();
        $summary = "Changement de statut demandé: {$oldStatus} → {$dto->new_status}. Confirmation requise.";

        return $this->createOutput($summary, [
            'type' => 'job_offer_changeset',
            'action' => 'change_status',
            'job_offer_id' => $dto->id,
            'title' => $offer->getTitle(),
            'old_status' => $oldStatus,
            'new_status' => $dto->new_status,
        ], true);
    }

    private function handleDelete(JobOfferPayload $dto, object $user): ToolOutput
    {
        if ($dto->id === null) {
            return $this->validationError("L'ID de l'offre est requis pour l'action 'delete'.");
        }

        $offer = $this->em->find(\App\Entity\Recrutement\JobOffer::class, $dto->id);

        if ($offer === null || $offer->isDeleted()) {
            return $this->createOutput("Offre d'emploi #{$dto->id} introuvable.");
        }

        $summary = "Suppression de l'offre #{$dto->id} ({$offer->getTitle()}) demandée. Confirmation requise.";

        return $this->createOutput($summary, [
            'type' => 'job_offer_changeset',
            'action' => 'delete',
            'job_offer_id' => $dto->id,
            'title' => $offer->getTitle(),
        ], true);
    }
}
