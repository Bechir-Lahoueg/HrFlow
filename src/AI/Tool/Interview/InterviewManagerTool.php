<?php

declare(strict_types=1);

namespace App\AI\Tool\Interview;

use App\AI\Domain\DTO\InterviewPayload;
use App\AI\Tool\AbstractEntityManagerTool;
use App\AI\Domain\ValueObject\ToolOutput;

final class InterviewManagerTool extends AbstractEntityManagerTool
{
    protected function getDtoClass(): string
    {
        return InterviewPayload::class;
    }

    protected function getToolName(): string
    {
        return 'manage_interviews';
    }

    protected function getToolDescription(): string
    {
        return 'Gère les entretiens : consultation (liste, détail), planification, modification, annulation et suppression.';
    }

    protected function handle(object $dto, object $user): ToolOutput
    {
        \assert($dto instanceof InterviewPayload);

        return match ($dto->action) {
            'list' => $this->handleList($dto, $user),
            'view' => $this->handleView($dto),
            'schedule' => $this->handleSchedule($dto, $user),
            'update' => $this->handleUpdate($dto, $user),
            'cancel' => $this->handleCancel($dto, $user),
            'delete' => $this->handleDelete($dto, $user),
            default => $this->validationError("Action '{$dto->action}' non reconnue."),
        };
    }

    private function handleList(InterviewPayload $dto, object $user): ToolOutput
    {
        $qb = $this->em->createQueryBuilder()
            ->select('i')
            ->from(\App\Entity\Recrutement\Interview::class, 'i')
            ->join('i.application', 'a')
            ->join('a.jobOffer', 'j')
            ->where('j.createdBy = :userId')
            ->setParameter('userId', $this->getUserId($user))
            ->orderBy('i.interviewDate', 'ASC');

        if ($dto->application_id !== null) {
            $qb->andWhere('a.id = :appId')
                ->setParameter('appId', $dto->application_id);
        }

        if ($dto->from_date !== null) {
            $qb->andWhere('i.interviewDate >= :fromDate')
                ->setParameter('fromDate', new \DateTime($dto->from_date));
        }

        if ($dto->to_date !== null) {
            $qb->andWhere('i.interviewDate <= :toDate')
                ->setParameter('toDate', new \DateTime($dto->to_date));
        }

        if ($dto->result !== null) {
            $qb->andWhere('i.result = :result')
                ->setParameter('result', $dto->result);
        }

        $qb->setMaxResults($dto->limit ?? 50);

        $interviews = $qb->getQuery()->getResult();

        $data = [];
        foreach ($interviews as $interview) {
            $data[] = [
                'id' => $interview->getId(),
                'application_id' => $interview->getApplication()?->getId(),
                'candidate_name' => $interview->getApplication()?->getCandidateName(),
                'job_title' => $interview->getApplication()?->getJobOffer()?->getTitle(),
                'type' => $interview->getType(),
                'interview_date' => $interview->getInterviewDate()?->format('Y-m-d H:i'),
                'result' => $interview->getResult(),
                'score' => $interview->getScore(),
            ];
        }

        $summary = \count($data) . ' entretien(s) trouvé(s).';
        if (\count($data) > 0) {
            $summary .= "\nListe des entretiens:\n";
            foreach ($data as $intv) {
                $summary .= \sprintf(
                    "- ID: %d | Candidat: %s | Offre: %s | Type: %s | Date: %s | Résultat: %s | Score: %s\n",
                    $intv['id'],
                    $intv['candidate_name'] ?? 'N/A',
                    $intv['job_title'] ?? 'N/A',
                    $intv['type'] ?? 'N/A',
                    $intv['interview_date'] ?? 'N/A',
                    $intv['result'] ?? 'N/A',
                    $intv['score'] !== null ? $intv['score'] . '/100' : 'N/A',
                );
            }
        }

        return $this->createOutput($summary, [
            'type' => 'interviews_table',
            'data' => $data,
        ]);
    }

    private function handleView(InterviewPayload $dto): ToolOutput
    {
        if ($dto->id === null) {
            return $this->validationError("L'ID de l'entretien est requis pour l'action 'view'.");
        }

        $interview = $this->em->find(\App\Entity\Recrutement\Interview::class, $dto->id);

        if ($interview === null || $interview->isDeleted()) {
            return $this->createOutput("Entretien #{$dto->id} introuvable.");
        }

        $data = [
            'id' => $interview->getId(),
            'application_id' => $interview->getApplication()?->getId(),
            'candidate_name' => $interview->getApplication()?->getCandidateName(),
            'job_title' => $interview->getApplication()?->getJobOffer()?->getTitle(),
            'type' => $interview->getType(),
            'interview_date' => $interview->getInterviewDate()?->format('Y-m-d H:i'),
            'meeting_link' => $interview->getMeetingLink(),
            'location' => $interview->getLocation(),
            'result' => $interview->getResult(),
            'score' => $interview->getScore(),
            'feedback' => $interview->getFeedback(),
        ];

        $summary = "Entretien #{$data['id']}: {$data['candidate_name']}\n"
            . "Type: {$data['type']} | Date: {$data['interview_date']}\n"
            . "Résultat: {$data['result']} | Score: {$data['score']}/100";

        return $this->createOutput($summary, [
            'type' => 'interview_card',
            'data' => $data,
        ]);
    }

    private function handleSchedule(InterviewPayload $dto, object $user): ToolOutput
    {
        if ($dto->application_id === null || $dto->type === null || $dto->date === null) {
            return $this->validationError("Les champs application_id, type et date sont requis pour planifier un entretien.");
        }

        $app = $this->em->find(\App\Entity\Recrutement\Application::class, $dto->application_id);

        if ($app === null || $app->isDeleted()) {
            return $this->createOutput("Candidature #{$dto->application_id} introuvable.");
        }

        $summary = "Entretien {$dto->type} planifié pour le {$dto->date} avec {$app->getCandidateName()}. Confirmation requise.";

        return $this->createOutput($summary, [
            'widget' => 'interview_scheduled',
            'application_id' => $dto->application_id,
            'candidate_name' => $app->getCandidateName(),
            'job_title' => $app->getJobOffer()?->getTitle(),
            'interview_type' => $dto->type,
            'date' => $dto->date,
            'duration' => $dto->duration ?? 60,
            'notes' => $dto->notes,
            'meeting_link' => $dto->meeting_link,
            'location' => $dto->location,
        ], true);
    }

    private function handleUpdate(InterviewPayload $dto, object $user): ToolOutput
    {
        if ($dto->id === null) {
            return $this->validationError("L'ID de l'entretien est requis pour l'action 'update'.");
        }

        $interview = $this->em->find(\App\Entity\Recrutement\Interview::class, $dto->id);

        if ($interview === null || $interview->isDeleted()) {
            return $this->createOutput("Entretien #{$dto->id} introuvable.");
        }

        $changes = [];
        if ($dto->date !== null) { $changes['date'] = $dto->date; }
        if ($dto->type !== null) { $changes['type'] = $dto->type; }
        if ($dto->duration !== null) { $changes['duration'] = $dto->duration; }
        if ($dto->notes !== null) { $changes['notes'] = $dto->notes; }
        if ($dto->meeting_link !== null) { $changes['meeting_link'] = $dto->meeting_link; }
        if ($dto->location !== null) { $changes['location'] = $dto->location; }

        if (empty($changes)) {
            return $this->createOutput("Aucune modification détectée pour l'entretien #{$dto->id}.");
        }

        $summary = "Modification de l'entretien #{$dto->id} demandée. Confirmation requise.";

        return $this->createOutput($summary, [
            'type' => 'interview_changeset',
            'action' => 'update',
            'interview_id' => $dto->id,
            'candidate_name' => $interview->getApplication()?->getCandidateName(),
            'payload' => $changes,
        ], true);
    }

    private function handleCancel(InterviewPayload $dto, object $user): ToolOutput
    {
        if ($dto->id === null) {
            return $this->validationError("L'ID de l'entretien est requis pour l'action 'cancel'.");
        }

        $interview = $this->em->find(\App\Entity\Recrutement\Interview::class, $dto->id);

        if ($interview === null || $interview->isDeleted()) {
            return $this->createOutput("Entretien #{$dto->id} introuvable.");
        }

        $summary = "Annulation de l'entretien #{$dto->id} ({$interview->getApplication()?->getCandidateName()}) demandée. Confirmation requise.";

        return $this->createOutput($summary, [
            'type' => 'interview_changeset',
            'action' => 'cancel',
            'interview_id' => $dto->id,
            'candidate_name' => $interview->getApplication()?->getCandidateName(),
        ], true);
    }

    private function handleDelete(InterviewPayload $dto, object $user): ToolOutput
    {
        if ($dto->id === null) {
            return $this->validationError("L'ID de l'entretien est requis pour l'action 'delete'.");
        }

        $interview = $this->em->find(\App\Entity\Recrutement\Interview::class, $dto->id);

        if ($interview === null || $interview->isDeleted()) {
            return $this->createOutput("Entretien #{$dto->id} introuvable.");
        }

        $summary = "Suppression de l'entretien #{$dto->id} demandée. Confirmation requise.";

        return $this->createOutput($summary, [
            'type' => 'interview_changeset',
            'action' => 'delete',
            'interview_id' => $dto->id,
            'candidate_name' => $interview->getApplication()?->getCandidateName(),
        ], true);
    }
}
