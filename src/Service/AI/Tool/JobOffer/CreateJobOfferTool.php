<?php

namespace App\Service\AI\Tool\JobOffer;

use App\Entity\Recrutement\JobOffer;
use App\Service\AI\Tool\ToolInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class CreateJobOfferTool implements ToolInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {}

    public function getName(): string
    {
        return 'create_job_offer';
    }

    public function getDefinition(): array
    {
        return [
            'name' => $this->getName(),
            'description' => "Crée une nouvelle offre d'emploi pour l'utilisateur RH courant.",
            'parameters' => [
                'type' => 'object',
                'properties' => (object)[
                    'title' => ['type' => 'string', 'description' => "Titre de l'offre"],
                    'description' => ['type' => 'string', 'description' => "Description de l'offre"],
                    'department' => ['type' => 'string', 'description' => "Département (optionnel)"],
                    'location' => ['type' => 'string', 'description' => "Localisation (optionnel)"],
                    'employment_type' => ['type' => 'string', 'description' => "Type d'emploi (CDI, CDD, Stage...) (optionnel)"],
                    'salary_min' => ['type' => 'number', 'description' => "Salaire minimum annuel (ex: 35000)"],
                    'salary_max' => ['type' => 'number', 'description' => "Salaire maximum annuel (ex: 55000)"],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['OPEN', 'CLOSED'],
                        'description' => "Statut de l'offre",
                        'default' => 'OPEN'
                    ],
                ],
                'required' => ['title', 'description', 'salary_min', 'salary_max'],
                'additionalProperties' => false
            ],
        ];
    }

    public function execute(array $args): mixed
    {
        $user = $this->security->getUser();
        if (!$user || !method_exists($user, 'getId')) {
            return ['error' => 'Not authenticated'];
        }

        $offer = new JobOffer();
        $offer->setCreatedBy($user->getId());
        $offer->setCreatedAt(new \DateTime());

        $offer->setTitle((string) $args['title']);
        $offer->setDescription((string) $args['description']);
        $offer->setDepartment($args['department'] ?? null);
        $offer->setLocation($args['location'] ?? null);
        $offer->setEmploymentType($args['employment_type'] ?? null);
        $offer->setSalaryMin((string) $args['salary_min']);
        $offer->setSalaryMax((string) $args['salary_max']);
        $offer->setStatus((string) ($args['status'] ?? 'OPEN'));
        $offer->setIsDeleted(false);

        $this->entityManager->persist($offer);
        $this->entityManager->flush();

        return [
            'status' => 'success',
            'job_id' => $offer->getId(),
            'title' => $offer->getTitle(),
            'message' => "✅ Offre créée (ID #{$offer->getId()})",
        ];
    }
}

