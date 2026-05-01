<?php

declare(strict_types=1);

namespace App\AI\Tool\Application;

use App\AI\Tool\ApplicationTool;
use Doctrine\ORM\EntityManagerInterface;

final class GetApplicationsTool extends ApplicationTool
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    protected function getToolName(): string
    {
        return 'get_applications';
    }

    protected function getToolDescription(): string
    {
        return 'Récupère la liste des candidatures avec filtres optionnels par statut et offre.';
    }

    protected function getParameters(): array
    {
        return [
            'job_offer_id' => ['type' => 'integer', 'description' => 'ID de l\'offre d\'emploi'],
            'status' => ['type' => 'string', 'description' => 'Statut de la candidature'],
            'limit' => ['type' => 'integer', 'description' => 'Nombre maximum de résultats'],
        ];
    }

    public function execute(array $args, object $user): \App\AI\Domain\ValueObject\ToolOutput
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('a')
            ->from(\App\Entity\Recrutement\Application::class, 'a')
            ->where('a.isDeleted = :deleted')
            ->setParameter('deleted', false);

        if (isset($args['job_offer_id'])) {
            $qb->andWhere('a.jobOffer = :jobId')
                ->setParameter('jobId', $args['job_offer_id']);
        }

        if (isset($args['status'])) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $args['status']);
        }

        $limit = $args['limit'] ?? 50;
        $qb->setMaxResults($limit);
        $qb->orderBy('a.appliedAt', 'DESC');

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
            ];
        }

        $summary = \count($data) . ' candidature(s) trouvée(s).';
        if (\count($data) > 0) {
            $summary .= "\nListe des candidatures:\n";
            foreach ($data as $app) {
                $summary .= sprintf(
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
}