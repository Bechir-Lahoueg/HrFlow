<?php

namespace App\Service\Formation;

use App\Entity\Rh\Employee;
use App\Enum\DeductionType;
use App\Repository\Formation\FormationRepository;
use App\Repository\Paie\DeductionRepository;

/**
 * FormationRecommendationService — Recommends formations based on
 * an employee's recent deductions/penalties pattern.
 */
final class FormationRecommendationService
{
    /**
     * Maps deduction types to relevant formation types/keywords.
     * Each deduction type suggests what kind of training could help.
     */
    private const DEDUCTION_TO_FORMATION_MAP = [
        'RETENUE_DISCIPLINAIRE' => ['Soft Skills', 'Management', 'Comportement'],
        'AVANCE_SALAIRE'        => ['Finance Personnelle', 'Gestion Budgétaire'],
        'CREDIT_REMBOURSEMENT'  => ['Finance Personnelle', 'Gestion Budgétaire'],
        'CHARGE_SYNDICALE'      => ['Droit du Travail', 'Relations Sociales'],
    ];

    public function __construct(
        private readonly DeductionRepository $deductionRepository,
        private readonly FormationRepository $formationRepository,
    ) {
    }

    /**
     * Get formation recommendations for an employee based on their deduction pattern.
     *
     * @return array<array{formation: \App\Entity\Formation\Formation, score: int, reason: string}>
     */
    public function getRecommendations(Employee $employee, int $rhId, int $recentMonths = 6, int $limit = 5): array
    {
        // Get recent deductions
        $since = new \DateTime();
        $since->modify("-{$recentMonths} months");

        $deductions = $this->deductionRepository->createQueryBuilder('d')
            ->where('d.employee = :empId')
            ->andWhere('d.dateDeduction >= :since')
            ->setParameter('empId', $employee->getId())
            ->setParameter('since', $since)
            ->orderBy('d.dateDeduction', 'DESC')
            ->getQuery()
            ->getResult();

        if (empty($deductions)) {
            return [];
        }

        // Count deductions by type
        $typeCounts = [];
        foreach ($deductions as $ded) {
            $typeKey = $ded->getTypeDeduction()->name;
            $typeCounts[$typeKey] = ($typeCounts[$typeKey] ?? 0) + 1;
        }

        // Determine relevant formation keywords
        $keywords = [];
        $reasons = [];
        foreach ($typeCounts as $typeKey => $count) {
            if (isset(self::DEDUCTION_TO_FORMATION_MAP[$typeKey])) {
                foreach (self::DEDUCTION_TO_FORMATION_MAP[$typeKey] as $kw) {
                    $keywords[$kw] = ($keywords[$kw] ?? 0) + $count;
                    $reasons[$kw] = sprintf('%d × %s', $count, DeductionType::from(
                        DeductionType::{$typeKey}->value
                    )->label());
                }
            }
        }

        // Also add generic recommendations if many deductions
        $totalDeductions = array_sum($typeCounts);
        if ($totalDeductions >= 3) {
            $keywords['Développement Personnel'] = ($keywords['Développement Personnel'] ?? 0) + $totalDeductions;
            $reasons['Développement Personnel'] = sprintf('%d déductions totales ces %d derniers mois', $totalDeductions, $recentMonths);
        }

        if (empty($keywords)) {
            return [];
        }

        // Find matching formations
        $allFormations = $this->formationRepository->findByRh($rhId);

        $scored = [];
        foreach ($allFormations as $formation) {
            $score = 0;
            $matchedReason = '';

            foreach ($keywords as $kw => $weight) {
                // Match against formation title, type, description, objectifs
                $haystack = mb_strtolower(
                    ($formation->getTitre() ?? '') . ' ' .
                    ($formation->getType() ?? '') . ' ' .
                    ($formation->getDescription() ?? '') . ' ' .
                    ($formation->getObjectifs() ?? '')
                );

                if (mb_strpos($haystack, mb_strtolower($kw)) !== false) {
                    $score += $weight;
                    $matchedReason = $reasons[$kw] ?? '';
                }
            }

            if ($score > 0) {
                $scored[] = [
                    'formation' => $formation,
                    'score' => $score,
                    'reason' => $matchedReason,
                ];
            }
        }

        // Sort by score descending
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * Get a summary of deduction patterns for an employee.
     *
     * @return array{totalDeductions: int, topTypes: array<string, int>, needsAttention: bool}
     */
    public function analyzeDeductionPattern(Employee $employee, int $recentMonths = 3): array
    {
        $since = new \DateTime();
        $since->modify("-{$recentMonths} months");

        $deductions = $this->deductionRepository->createQueryBuilder('d')
            ->where('d.employee = :empId')
            ->andWhere('d.dateDeduction >= :since')
            ->setParameter('empId', $employee->getId())
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $typeCounts = [];
        foreach ($deductions as $ded) {
            $label = $ded->getTypeDeduction()->label();
            $typeCounts[$label] = ($typeCounts[$label] ?? 0) + 1;
        }

        arsort($typeCounts);

        return [
            'totalDeductions' => count($deductions),
            'topTypes' => array_slice($typeCounts, 0, 3, true),
            'needsAttention' => count($deductions) >= 3,
        ];
    }
}
