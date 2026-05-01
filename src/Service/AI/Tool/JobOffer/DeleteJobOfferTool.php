<?php

namespace App\Service\AI\Tool\JobOffer;

use App\Repository\Recrutement\JobOfferRepository;
use App\Service\AI\Tool\ToolInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class DeleteJobOfferTool implements ToolInterface
{
    public function __construct(
        private JobOfferRepository $jobOfferRepository,
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {}

    public function getName(): string
    {
        return 'delete_job_offer';
    }

    public function getDefinition(): array
    {
        return [
            'name' => $this->getName(),
            'description' => "Supprime (soft delete) une offre d'emploi (propriétaire RH uniquement).",
            'parameters' => [
                'type' => 'object',
                'properties' => (object)[
                    'job_id' => ['type' => 'integer', 'description' => "ID de l'offre"],
                ],
                'required' => ['job_id'],
                'additionalProperties' => false
            ],
        ];
    }

    public function execute(array $args): mixed
    {
        $user = $this->security->getUser();
        if (!$user) {
            return ['error' => 'Not authenticated'];
        }

        $jobId = (int) $args['job_id'];
        $offer = $this->jobOfferRepository->findOneByRh($jobId, $user);
        if (!$offer) {
            return ['error' => "Offre #{$jobId} introuvable ou non autorisée."];
        }

        $offer->setIsDeleted(true);
        $this->entityManager->flush();

        return [
            'status' => 'success',
            'job_id' => $jobId,
            'message' => "✅ Offre #{$jobId} supprimée (soft delete).",
        ];
    }
}

