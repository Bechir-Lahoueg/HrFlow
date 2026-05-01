<?php

declare(strict_types=1);

namespace App\AI\Tool\Interview;

use App\AI\Tool\InterviewTool;
use Doctrine\ORM\EntityManagerInterface;

final class GetInterviewsTool extends InterviewTool
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    protected function getToolName(): string
    {
        return 'get_interviews';
    }

    protected function getToolDescription(): string
    {
        return 'Récupère la liste des entretiens planifiés avec filtres optionnels.';
    }

    protected function getParameters(): array
    {
        return [
            'application_id' => ['type' => 'integer', 'description' => 'ID de la candidature'],
            'status' => ['type' => 'string', 'description' => 'Statut de l\'entretien'],
            'from_date' => ['type' => 'string', 'description' => 'Date de début (Y-m-d)'],
            'to_date' => ['type' => 'string', 'description' => 'Date de fin (Y-m-d)'],
            'limit' => ['type' => 'integer', 'description' => 'Nombre maximum de résultats'],
        ];
    }

    public function execute(array $args, object $user): \App\AI\Domain\ValueObject\ToolOutput
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('i')
            ->from(\App\Entity\Recrutement\Interview::class, 'i')
            ->join('i.application', 'a')
            ->where('i.id IS NOT NULL');

        if (isset($args['application_id'])) {
            $qb->andWhere('a.id = :appId')
                ->setParameter('appId', $args['application_id']);
        }

        if (isset($args['from_date'])) {
            $qb->andWhere('i.interviewDate >= :fromDate')
                ->setParameter('fromDate', new \DateTime($args['from_date']));
        }

        if (isset($args['to_date'])) {
            $qb->andWhere('i.interviewDate <= :toDate')
                ->setParameter('toDate', new \DateTime($args['to_date']));
        }

        $limit = $args['limit'] ?? 50;
        $qb->setMaxResults($limit);
        $qb->orderBy('i.interviewDate', 'ASC');

        $interviews = $qb->getQuery()->getResult();

        $data = [];
        foreach ($interviews as $interview) {
            $data[] = [
                'id' => $interview->getId(),
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
                $summary .= sprintf(
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
}