<?php

namespace App\Service\AI\Tool\JobOffer;

use App\Service\AI\Tool\ToolInterface;
use App\Repository\Recrutement\ApplicationRepository;
use App\Repository\Recrutement\JobOfferRepository;
use Symfony\Bundle\SecurityBundle\Security;

class GetApplicationsPerJobOfferTool implements ToolInterface
{
    public function __construct(
        private JobOfferRepository $jobOfferRepository,
        private ApplicationRepository $applicationRepository,
        private Security $security
    ) {}

    public function getName(): string
    {
        return 'get_applications_per_job_offer';
    }

    public function getDefinition(): array
    {
        return [
            'name' => $this->getName(),
            'description' => "Retourne le nombre de candidatures par offre d'emploi (pour l'utilisateur RH courant).",
            'parameters' => [
                'type' => 'object',
                'properties' => (object)[
                    'status' => [
                        'type' => 'string',
                        'enum' => ['OPEN', 'CLOSED'],
                        'description' => "Filtrer les offres par statut (optionnel)."
                    ],
                    'include_deleted' => [
                        'type' => 'boolean',
                        'description' => "Inclure les offres supprimées (par défaut: false).",
                        'default' => false
                    ],
                ],
                'additionalProperties' => false
            ]
        ];
    }

    public function execute(array $args): mixed
    {
        $user = $this->security->getUser();
        if (!$user || !method_exists($user, 'getId')) {
            return ['error' => 'Not authenticated'];
        }

        $rhId = $user->getId();
        $status = $args['status'] ?? null;
        $includeDeleted = (bool) ($args['include_deleted'] ?? false);

        if ($includeDeleted) {
            // When including deleted, repository helper isn't available; fallback to a broader fetch.
            $offers = $this->jobOfferRepository->findBy(['createdBy' => $rhId], ['createdAt' => 'DESC']);
        } else {
            // Uses the existing RH filter in repository (non-deleted).
            $offers = $this->jobOfferRepository->findByRh($user, $status);
        }

        $rows = [];
        $totalApplications = 0;

        foreach ($offers as $offer) {
            if (!$offer) {
                continue;
            }

            // Respect status filter when include_deleted is true (findByRh already does it otherwise)
            if ($includeDeleted && $status && method_exists($offer, 'getStatus') && $offer->getStatus() !== $status) {
                continue;
            }

            $apps = $this->applicationRepository->findByJobOffer($offer->getId(), $user);
            $count = is_array($apps) ? count($apps) : 0;
            $totalApplications += $count;

            $rows[] = [
                'job_id' => $offer->getId(),
                'title' => $offer->getTitle(),
                'status' => $offer->getStatus(),
                'location' => $offer->getLocation(),
                'department' => $offer->getDepartment(),
                'applications_count' => $count,
                'is_deleted' => method_exists($offer, 'isDeleted') ? $offer->isDeleted() : false,
            ];
        }

        usort($rows, fn ($a, $b) => ($b['applications_count'] ?? 0) <=> ($a['applications_count'] ?? 0));

        return [
            'rows' => $rows,
            'total_offers' => count($rows),
            'total_applications' => $totalApplications,
        ];
    }
}

