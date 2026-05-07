<?php

declare(strict_types=1);

namespace App\AI\Tool\Application;

use App\AI\Domain\DTO\ApplicationPayload;
use App\AI\Tool\AbstractEntityManagerTool;
use App\AI\Domain\ValueObject\ToolOutput;

final class ApplicationManagerTool extends AbstractEntityManagerTool
{
    protected function getDtoClass(): string
    {
        return ApplicationPayload::class;
    }

    protected function getToolName(): string
    {
        return 'manage_applications';
    }

    protected function getToolDescription(): string
    {
        return 'Gère les candidatures : liste, détail, changement de statut, classement des candidats, création et suppression.';
    }

    protected function handle(object $dto, object $user): ToolOutput
    {
        \assert($dto instanceof ApplicationPayload);

        return match ($dto->action) {
            'list' => $this->handleList($dto, $user),
            'view' => $this->handleView($dto),
            'move' => $this->handleMove($dto, $user),
            'rank' => $this->handleRank($dto, $user),
            'create' => $this->handleCreate($dto, $user),
            'delete' => $this->handleDelete($dto, $user),
            default => $this->validationError("Action '{$dto->action}' non reconnue."),
        };
    }

    private function handleList(ApplicationPayload $dto, object $user): ToolOutput
    {
        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->join('a.jobOffer', 'j')
            ->where('a.isDeleted = :deleted')
            ->andWhere('j.createdBy = :userId')
            ->setParameter('deleted', false)
            ->setParameter('userId', $this->getUserId($user))
            ->orderBy('a.appliedAt', 'DESC');

        if ($dto->job_offer_id !== null) {
            $qb->andWhere('a.jobOffer = :jobId')
                ->setParameter('jobId', $dto->job_offer_id);
        }

        if ($dto->status !== null) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $dto->status);
        }

        $qb->setMaxResults($dto->limit ?? 50);

        $applications = $qb->getQuery()->getResult();

        $data = [];
        foreach ($applications as $app) {
            $data[] = [
                'id' => $app->getId(),
                'candidate_name' => $app->getCandidateName(),
                'email' => $app->getEmailAddress(),
                'job_title' => $app->getJobOffer()?->getTitle(),
                'job_offer_id' => $app->getJobOffer()?->getId(),
                'status' => $app->getStatus(),
                'status_label' => $app->getStatusLabel(),
                'applied_at' => $app->getAppliedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        $summary = \count($data) . ' candidature(s) trouvée(s).';
        if (\count($data) > 0) {
            $summary .= "\nListe des candidatures:\n";
            foreach ($data as $app) {
                $summary .= \sprintf(
                    "- ID: %d | Nom: %s | Email: %s | Offre: %s | Statut: %s | Date: %s\n",
                    $app['id'],
                    $app['candidate_name'],
                    $app['email'] ?? 'N/A',
                    $app['job_title'] ?? 'N/A',
                    $app['status'],
                    $app['applied_at'],
                );
            }
        }

        return $this->createOutput($summary, [
            'type' => 'applications_table',
            'data' => $data,
        ]);
    }

    private function handleView(ApplicationPayload $dto): ToolOutput
    {
        if ($dto->id === null) {
            return $this->validationError("L'ID de la candidature est requis pour l'action 'view'.");
        }

        $app = $this->em->find(\App\Entity\Recrutement\Application::class, $dto->id);

        if ($app === null || $app->isDeleted()) {
            return $this->createOutput("Candidature #{$dto->id} introuvable.");
        }

        $data = [
            'id' => $app->getId(),
            'candidate_name' => $app->getCandidateName(),
            'email' => $app->getEmailAddress(),
            'job_title' => $app->getJobOffer()?->getTitle(),
            'job_offer_id' => $app->getJobOffer()?->getId(),
            'status' => $app->getStatus(),
            'status_label' => $app->getStatusLabel(),
            'applied_at' => $app->getAppliedAt()?->format('Y-m-d H:i:s'),
            'department' => $app->getDepartment(),
            'experience_level' => $app->getExperienceLevel(),
        ];

        $summary = "Candidature #{$data['id']}: {$data['candidate_name']}\n"
            . "Offre: {$data['job_title']}\n"
            . "Statut: {$data['status_label']}\n"
            . "Email: {$data['email']}\n"
            . "Date: {$data['applied_at']}";

        return $this->createOutput($summary, [
            'type' => 'application_card',
            'data' => $data,
        ]);
    }

    private function handleMove(ApplicationPayload $dto, object $user): ToolOutput
    {
        if ($dto->id === null || $dto->new_status === null) {
            return $this->validationError("L'ID et le nouveau statut sont requis pour l'action 'move'.");
        }

        $validStatuses = ['PENDING', 'REVIEWING', 'INTERVIEW', 'OFFER', 'HIRED', 'REJECTED'];
        $newStatus = \strtoupper($dto->new_status);

        if (!\in_array($newStatus, $validStatuses)) {
            return $this->createOutput(
                "Statut invalide: {$newStatus}. Statuts possibles: " . \implode(', ', $validStatuses),
            );
        }

        $app = $this->em->find(\App\Entity\Recrutement\Application::class, $dto->id);

        if ($app === null || $app->isDeleted()) {
            return $this->createOutput("Candidature #{$dto->id} introuvable.");
        }

        $oldStatus = $app->getStatus();
        $summary = "Déplacement de statut demandé: {$oldStatus} → {$newStatus}. Confirmation requise.";

        return $this->createOutput($summary, [
            'type' => 'stage_move',
            'application_id' => $dto->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'candidate_name' => $app->getCandidateName(),
            'job_title' => $app->getJobOffer()?->getTitle(),
        ], true);
    }

    private function handleRank(ApplicationPayload $dto, object $user): ToolOutput
    {
        if ($dto->job_offer_id === null) {
            return $this->validationError("L'ID de l'offre est requis pour l'action 'rank'.");
        }

        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->join('a.jobOffer', 'j')
            ->where('a.isDeleted = :deleted')
            ->andWhere('j.id = :jobId')
            ->andWhere('j.createdBy = :userId')
            ->setParameter('deleted', false)
            ->setParameter('jobId', $dto->job_offer_id)
            ->setParameter('userId', $this->getUserId($user))
            ->orderBy('a.appliedAt', 'DESC');

        $qb->setMaxResults($dto->limit ?? 10);

        $applications = $qb->getQuery()->getResult();

        $candidates = [];
        $rank = 1;
        foreach ($applications as $app) {
            $interviews = $app->getInterviews();
            $totalScore = 0;
            $count = 0;
            foreach ($interviews as $interview) {
                if (!$interview->isDeleted() && $interview->getScore() !== null) {
                    $totalScore += $interview->getScore();
                    ++$count;
                }
            }
            $avgScore = $count > 0 ? (int) \round($totalScore / $count) : 50;
            $candidates[] = [
                'rank' => $rank++,
                'id' => $app->getId(),
                'name' => $app->getCandidateName(),
                'role' => $app->getJobOffer()?->getTitle(),
                'score' => $avgScore,
                'email' => $app->getEmailAddress(),
            ];
        }

        \usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
        foreach ($candidates as $i => $c) {
            $candidates[$i]['rank'] = $i + 1;
        }

        $summary = \count($candidates) . ' candidat(s) classé(s) pour l\'offre #' . $dto->job_offer_id;
        foreach ($candidates as $c) {
            $summary .= \sprintf(
                "\n#%d — %s | Score: %d/100 | Email: %s",
                $c['rank'],
                $c['name'],
                $c['score'],
                $c['email'] ?? 'N/A',
            );
        }

        return $this->createOutput($summary, [
            'type' => 'candidate_grid',
            'candidates' => $candidates,
        ]);
    }

    private function handleCreate(ApplicationPayload $dto, object $user): ToolOutput
    {
        if ($dto->job_offer_id === null) {
            return $this->validationError("L'ID de l'offre est requis pour créer une candidature.");
        }

        $offer = $this->em->find(\App\Entity\Recrutement\JobOffer::class, $dto->job_offer_id);
        if ($offer === null || $offer->isDeleted()) {
            return $this->createOutput("Offre d'emploi #{$dto->job_offer_id} introuvable.");
        }

        $summary = "Création de candidature demandée pour l'offre '{$offer->getTitle()}'. Confirmation requise.";

        return $this->createOutput($summary, [
            'type' => 'application_changeset',
            'action' => 'create',
            'payload' => [
                'job_offer_id' => $dto->job_offer_id,
                'candidate_name' => $dto->candidate_name,
                'email' => $dto->email,
            ],
        ], true);
    }

    private function handleDelete(ApplicationPayload $dto, object $user): ToolOutput
    {
        if ($dto->id === null) {
            return $this->validationError("L'ID de la candidature est requis pour l'action 'delete'.");
        }

        $app = $this->em->find(\App\Entity\Recrutement\Application::class, $dto->id);

        if ($app === null || $app->isDeleted()) {
            return $this->createOutput("Candidature #{$dto->id} introuvable.");
        }

        $summary = "Suppression de la candidature #{$dto->id} ({$app->getCandidateName()}) demandée. Confirmation requise.";

        return $this->createOutput($summary, [
            'type' => 'application_changeset',
            'action' => 'delete',
            'application_id' => $dto->id,
            'candidate_name' => $app->getCandidateName(),
        ], true);
    }
}
