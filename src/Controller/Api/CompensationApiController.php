<?php

namespace App\Controller\Api;

use App\Service\Paie\CompensationDefaultsService;
use App\Service\Paie\CompensationValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api', name: 'api_')]
final class CompensationApiController extends AbstractController
{
    #[Route('/prime-default/{typeName}', name: 'prime_default', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function getPrimeDefault(
        string $typeName,
        CompensationDefaultsService $compensationService
    ): JsonResponse {
        $montant = $compensationService->getPrimeDefault(urldecode($typeName));
        
        return $this->json([
            'type' => $typeName,
            'category' => 'prime',
            'montant' => $montant,
        ]);
    }

    #[Route('/deduction-default/{typeName}', name: 'deduction_default', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function getDeductionDefault(
        string $typeName,
        CompensationDefaultsService $compensationService
    ): JsonResponse {
        $montant = $compensationService->getDeductionDefault(urldecode($typeName));
        
        return $this->json([
            'type' => $typeName,
            'category' => 'deduction',
            'montant' => $montant,
        ]);
    }

    #[Route('/compensation-defaults', name: 'compensation_defaults', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function getAllDefaults(
        CompensationDefaultsService $compensationService
    ): JsonResponse {
        return $this->json([
            'primes' => $compensationService->getAllPrimeDefaults(),
            'deductions' => $compensationService->getAllDeductionDefaults(),
        ]);
    }

    #[Route('/prime-validation/{typeName}', name: 'prime_validation', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function getPrimeValidation(
        string $typeName,
        CompensationValidationService $validationService
    ): JsonResponse {
        $range = $validationService->getPrimeRange(urldecode($typeName));
        
        if (!$range) {
            return $this->json(['error' => 'Prime type not found'], 404);
        }

        return $this->json([
            'type' => $typeName,
            'category' => 'prime',
            'min' => $range['min'],
            'max' => $range['max'],
        ]);
    }

    #[Route('/deduction-validation/{typeName}', name: 'deduction_validation', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function getDeductionValidation(
        string $typeName,
        CompensationValidationService $validationService
    ): JsonResponse {
        $range = $validationService->getDeductionRange(urldecode($typeName));
        
        if (!$range) {
            return $this->json(['error' => 'Deduction type not found'], 404);
        }

        return $this->json([
            'type' => $typeName,
            'category' => 'deduction',
            'min' => $range['min'],
            'max' => $range['max'],
        ]);
    }

    #[Route('/compensation-validations', name: 'compensation_validations', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function getAllValidations(
        CompensationValidationService $validationService
    ): JsonResponse {
        return $this->json([
            'primes' => $validationService->getAllPrimeRanges(),
            'deductions' => $validationService->getAllDeductionRanges(),
        ]);
    }
}
