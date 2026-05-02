<?php

namespace App\Service\Recrutement;

use App\Entity\Recrutement\JobOffer;

class JobOfferManager
{
    public function validate(JobOffer $jobOffer): bool
    {
        if (empty(trim($jobOffer->getTitle() ?? ''))) {
            throw new \InvalidArgumentException('Le titre est obligatoire');
        }

        $salaryMin = (float) $jobOffer->getSalaryMin();
        $salaryMax = (float) $jobOffer->getSalaryMax();

        if ($salaryMin < 0) {
            throw new \InvalidArgumentException('Le salaire minimum ne peut pas être négatif');
        }

        if ($salaryMax < 0) {
            throw new \InvalidArgumentException('Le salaire maximum ne peut pas être négatif');
        }

        if ($salaryMax < $salaryMin) {
            throw new \InvalidArgumentException('Le salaire maximum doit être supérieur ou égal au salaire minimum');
        }

        return true;
    }
}
