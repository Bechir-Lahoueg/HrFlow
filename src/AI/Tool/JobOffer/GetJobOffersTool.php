<?php

declare(strict_types=1);

namespace App\AI\Tool\JobOffer;

use App\AI\Tool\JobOfferTool;
use Doctrine\ORM\EntityManagerInterface;

final class GetJobOffersTool extends JobOfferTool
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    protected function getToolName(): string
    {
        return 'get_job_offers';
    }

    protected function getToolDescription(): string
    {
        return 'Récupère la liste des offres d\'emploi.';
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function getParameters(): array
    {
        return [
            'status' => ['type' => 'string', 'description' => 'Statut de l\'offre'],
            'department' => ['type' => 'string', 'description' => 'Département'],
            'limit' => ['type' => 'integer', 'description' => 'Nombre maximum de résultats'],
        ];
    }

    /**
     * @param array<string, mixed> $args
     */
    public function execute(array $args, object $user): \App\AI\Domain\ValueObject\ToolOutput
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('j')
            ->from(\App\Entity\Recrutement\JobOffer::class, 'j')
            ->where('j.isDeleted = :deleted')
            ->setParameter('deleted', false);

        if (isset($args['status'])) {
            $qb->andWhere('j.status = :status')
                ->setParameter('status', $args['status']);
        }

        if (isset($args['department'])) {
            $qb->andWhere('j.department = :dept')
                ->setParameter('dept', $args['department']);
        }

        $limit = $args['limit'] ?? 50;
        $qb->setMaxResults($limit);
        $qb->orderBy('j.createdAt', 'DESC');

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
                $summary .= sprintf(
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
}