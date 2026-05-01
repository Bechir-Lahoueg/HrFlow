<?php

namespace App\Service\AI\Tool\JobOffer;

use App\Repository\Recrutement\JobOfferRepository;
use App\Service\AI\Tool\ToolInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class UpdateJobOfferTool implements ToolInterface
{
    public function __construct(
        private JobOfferRepository $jobOfferRepository,
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {}

    public function getName(): string
    {
        return 'update_job_offer';
    }

    public function getDefinition(): array
    {
        return [
            'name' => $this->getName(),
            'description' => "Met à jour une offre d'emploi existante (propriétaire RH uniquement).",
            'parameters' => [
                'type' => 'object',
                'properties' => (object)[
                    'job_id' => ['type' => 'integer', 'description' => "ID de l'offre"],
                    'title' => ['type' => 'string', 'description' => "Nouveau titre (optionnel)"],
                    'description' => ['type' => 'string', 'description' => "Nouvelle description (optionnel)"],
                    'department' => ['type' => 'string', 'description' => "Département (optionnel)"],
                    'location' => ['type' => 'string', 'description' => "Localisation (optionnel)"],
                    'employment_type' => ['type' => 'string', 'description' => "Type d'emploi (optionnel)"],
                    'salary_min' => ['type' => 'number', 'description' => "Nouveau salaire minimum (optionnel)"],
                    'salary_max' => ['type' => 'number', 'description' => "Nouveau salaire maximum (optionnel)"],
                    'status' => ['type' => 'string', 'enum' => ['OPEN', 'CLOSED'], 'description' => "Statut (optionnel)"],
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

        $changed = [];
        foreach (['title', 'description', 'department', 'location', 'employment_type', 'salary_min', 'salary_max', 'status'] as $field) {
            if (!array_key_exists($field, $args)) {
                continue;
            }
            $changed[] = $field;
        }

        if (array_key_exists('title', $args)) {
            $offer->setTitle((string) $args['title']);
        }
        if (array_key_exists('description', $args)) {
            $offer->setDescription((string) $args['description']);
        }
        if (array_key_exists('department', $args)) {
            $offer->setDepartment($args['department'] !== '' ? (string) $args['department'] : null);
        }
        if (array_key_exists('location', $args)) {
            $offer->setLocation($args['location'] !== '' ? (string) $args['location'] : null);
        }
        if (array_key_exists('employment_type', $args)) {
            $offer->setEmploymentType($args['employment_type'] !== '' ? (string) $args['employment_type'] : null);
        }
        if (array_key_exists('salary_min', $args)) {
            $offer->setSalaryMin((string) $args['salary_min']);
        }
        if (array_key_exists('salary_max', $args)) {
            $offer->setSalaryMax((string) $args['salary_max']);
        }
        if (array_key_exists('status', $args)) {
            $offer->setStatus((string) $args['status']);
        }

        $this->entityManager->flush();

        return [
            'status' => 'success',
            'job_id' => $offer->getId(),
            'changed_fields' => $changed,
            'message' => "✅ Offre #{$offer->getId()} mise à jour.",
        ];
    }
}

