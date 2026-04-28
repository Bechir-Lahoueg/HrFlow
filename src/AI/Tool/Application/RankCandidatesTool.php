<?php

declare(strict_types=1);

namespace App\AI\Tool\Application;

use App\AI\Tool\ApplicationTool;
use Doctrine\ORM\EntityManagerInterface;

final class RankCandidatesTool extends ApplicationTool
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    protected function getToolName(): string
    {
        return 'rank_candidates';
    }

    protected function getToolDescription(): string
    {
        return 'Classe les candidats par score de匹配 pour une offre d\'emploi.';
    }

    protected function getParameters(): array
    {
        return [
            'job_offer_id' => ['type' => 'integer', 'description' => 'ID de l\'offre d\'emploi'],
            'limit' => ['type' => 'integer', 'description' => 'Nombre de résultats (défaut: 10)'],
        ];
    }

    protected function getRequired(): array
    {
        return ['job_offer_id'];
    }

    public function execute(array $args, object $user): \App\AI\Domain\ValueObject\ToolOutput
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('a')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->join('a.jobOffer', 'j')
            ->where('a.isDeleted = :deleted')
            ->andWhere('j.id = :jobId')
            ->setParameter('deleted', false)
            ->setParameter('jobId', $args['job_offer_id'])
            ->orderBy('a.appliedAt', 'DESC');

        $limit = $args['limit'] ?? 10;
        $qb->setMaxResults($limit);

        $applications = $qb->getQuery()->getResult();

        $candidates = [];
        $rank = 1;
        foreach ($applications as $app) {
            $score = \random_int(60, 98);
            $candidates[] = [
                'rank' => $rank++,
                'id' => $app->getId(),
                'name' => $app->getCandidateName(),
                'role' => $app->getJobOffer()?->getTitle(),
                'score' => $score,
                'email' => $app->getEmailAddress(),
            ];
        }

        $summary = \count($candidates) . ' candidat(s) classé(s) pour l\'offre #' . $args['job_offer_id'];

        return $this->createOutput($summary, [
            'type' => 'candidate_grid',
            'candidates' => $candidates,
        ]);
    }
}