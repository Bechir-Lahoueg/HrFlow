<?php

namespace App\Service\Recrutement;

use App\Entity\Recrutement\Candidate;

class CandidateManager
{
    public function validate(Candidate $candidate): bool
    {
        if (empty($candidate->getEmail()) || !filter_var($candidate->getEmail(), FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email invalide');
        }

        if (strlen($candidate->getPassword() ?? '') < 8) {
            throw new \InvalidArgumentException('Le mot de passe doit contenir au moins 8 caractères');
        }

        return true;
    }
}
