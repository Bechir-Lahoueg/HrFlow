<?php

declare(strict_types=1);

namespace App\Service\Recrutement;

use App\AI\Domain\DTO\InterviewQuery;
use Doctrine\ORM\EntityManagerInterface;

final class InterviewService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function list(InterviewQuery $query): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('i')
            ->from(\App\Entity\Recrutement\Interview::class, 'i')
            ->orderBy('i.scheduledAt', 'ASC');

        $this->applyFilters($qb, $query);

        $interviews = $qb->getQuery()->getResult();

        $data = [];
        foreach ($interviews as $interview) {
            $data[] = [
                'id' => $interview->getId(),
                'application_id' => $interview->getApplication()?->getId(),
                'candidate_name' => $interview->getApplication()?->getCandidateName(),
                'scheduled_at' => $interview->getScheduledAt()?->format('Y-m-d H:i:s'),
                'result' => $interview->getResult(),
                'notes' => $interview->getNotes(),
            ];
        }

        return $data;
    }

    public function schedule(int $applicationId, string $scheduledAt, string $notes = ''): array
    {
        $app = $this->em->getRepository(\App\Entity\Recrutement\Application::class)->find($applicationId);
        if ($app === null || $app->isDeleted()) {
            return ['error' => 'Application not found'];
        }

        $interview = new \App\Entity\Recrutement\Interview();
        $interview->setApplication($app);
        $interview->setScheduledAt(new \DateTime($scheduledAt));
        $interview->setNotes($notes);

        $this->em->persist($interview);
        $this->em->flush();

        return [
            'id' => $interview->getId(),
            'candidate_name' => $app->getCandidateName(),
            'scheduled_at' => $interview->getScheduledAt()->format('Y-m-d H:i:s'),
        ];
    }

    public function getStats(): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('i.result', 'COUNT(i.id) as cnt')
            ->from(\App\Entity\Recrutement\Interview::class, 'i')
            ->where('i.result IS NOT NULL')
            ->groupBy('i.result');

        $results = $qb->getQuery()->getResult();

        $stats = [];
        foreach ($results as $row) {
            $stats[$row['result'] ?? 'N/A'] = (int) $row['cnt'];
        }

        return $stats;
    }

    private function applyFilters($qb, InterviewQuery $query): void
    {
        if ($query->applicationId !== null) {
            $qb->andWhere('i.application = :appId')
                ->setParameter('appId', $query->applicationId);
        }

        if ($query->dateFrom !== null) {
            $qb->andWhere('i.scheduledAt >= :from')
                ->setParameter('from', new \DateTime($query->dateFrom));
        }

        if ($query->dateTo !== null) {
            $qb->andWhere('i.scheduledAt <= :to')
                ->setParameter('to', new \DateTime($query->dateTo));
        }

        if ($query->result !== null) {
            $qb->andWhere('i.result = :result')
                ->setParameter('result', $query->result);
        }
    }
}
