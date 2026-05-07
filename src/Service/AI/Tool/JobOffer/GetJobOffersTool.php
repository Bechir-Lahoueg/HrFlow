<?php

namespace App\Service\AI\Tool\JobOffer;

use App\Service\AI\Tool\ToolInterface;
use App\Repository\Recrutement\JobOfferRepository;
use Symfony\Bundle\SecurityBundle\Security;

class GetJobOffersTool implements ToolInterface
{
    public function __construct(
        private JobOfferRepository $jobOfferRepository,
        private Security $security
    ) {}

    public function getName(): string
    {
        return 'get_job_offers';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'get_job_offers',
            'description' => 'Lists job offers with their IDs, titles, and statuses for the current user.',
            'parameters' => [
                'type' => 'object',
                'properties' => (object)[
                    'status' => [
                        'type' => 'string',
                        'enum' => ['OPEN', 'CLOSED'],
                        'description' => 'Filter by status'
                    ],
                    'title_search' => [
                        'type' => 'string',
                        'description' => 'Search by job title'
                    ]
                ],
                'additionalProperties' => false
            ]
        ];
    }

    public function execute(array $args): mixed
    {
        $user = $this->security->getUser();
        if (!$user) return ['error' => 'Not authenticated'];

        $criteria = ['isDeleted' => false];
        
        if (isset($args['status'])) {
            $criteria['status'] = $args['status'];
        }

        // Filter by current RH user ID
        $criteria['createdBy'] = method_exists($user, 'getId') ? $user->getId() : null;

        $offers = $this->jobOfferRepository->findBy($criteria);
        
        $result = [];
        foreach ($offers as $offer) {
            if (isset($args['title_search']) && !empty($args['title_search'])) {
                if (!str_contains(strtolower($offer->getTitle() ?? ''), strtolower((string) $args['title_search']))) {
                    continue;
                }
            }

            $result[] = [
                'id' => $offer->getId(),
                'title' => $offer->getTitle(),
                'status' => $offer->getStatus(),
                'department' => $offer->getDepartment(),
                'location' => $offer->getLocation()
            ];
        }

        return $result;
    }
}
