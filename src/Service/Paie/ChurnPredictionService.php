<?php

namespace App\Service\Paie;

use App\Repository\Paie\FichePaieRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ChurnPredictionService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly FichePaieRepository $fichePaieRepository,
        private readonly string $apiUrl,
    ) {
    }

    /**
     * @return array{score: float, label: string, source: string, indicator: string}
     */
    public function predictForEmployee(int $employeeId): array
    {
        $history = $this->buildHistory($employeeId);

        if ($this->apiUrl !== '' && !empty($history)) {
            try {
                $response = $this->httpClient->request('POST', $this->apiUrl, [
                    'timeout' => 4,
                    'json' => [
                        'employee_id' => $employeeId,
                        'history' => $history,
                    ],
                ]);

                if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                    $decoded = $response->toArray(false);
                    $raw = $decoded['churn_probability'] ?? $decoded['score'] ?? null;

                    if (is_numeric($raw)) {
                        $score = $this->normalizeScore((float) $raw);
                        return $this->decorate($score, 'api');
                    }
                }
            } catch (\Throwable) {
                // Silent fallback: we still return a deterministic local estimation.
            }
        }

        return $this->decorate($this->fallbackScore($history), 'fallback');
    }

    /**
     * @return array<int, array{year:int,month:int,gross:float,net:float,primes:float,deductions:float,deduction_ratio:float}>
     */
    private function buildHistory(int $employeeId): array
    {
        $rows = $this->fichePaieRepository
            ->createQueryBuilder('fp')
            ->where('fp.employee = :employeeId')
            ->setParameter('employeeId', $employeeId)
            ->orderBy('fp.annee', 'DESC')
            ->addOrderBy('fp.mois', 'DESC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();

        $history = [];
        foreach ($rows as $row) {
            $gross = (float) $row->getSalaireBrut();
            $net = (float) $row->getSalaireNet();
            $primes = (float) $row->getTotalPrimes();
            $deductions = (float) $row->getTotalDeductions();

            $history[] = [
                'year' => (int) $row->getAnnee(),
                'month' => (int) $row->getMois(),
                'gross' => $gross,
                'net' => $net,
                'primes' => $primes,
                'deductions' => $deductions,
                'deduction_ratio' => $gross > 0 ? ($deductions / $gross) : 0.0,
            ];
        }

        return $history;
    }

    /**
     * @param array<int, array{year:int,month:int,gross:float,net:float,primes:float,deductions:float,deduction_ratio:float}> $history
     */
    private function fallbackScore(array $history): float
    {
        if ($history === []) {
            return 25.0;
        }

        $latest = $history[0];
        $score = 18.0;

        $score += min(32.0, $latest['deduction_ratio'] * 100.0 * 0.45);

        if ($latest['gross'] > 0 && ($latest['primes'] / $latest['gross']) < 0.03) {
            $score += 9.0;
        }

        if (count($history) >= 2) {
            $previous = $history[1];
            if ($latest['net'] < $previous['net']) {
                $delta = ($previous['net'] - $latest['net']) / max(1.0, $previous['net']);
                $score += min(18.0, $delta * 100.0 * 0.7);
            }
        } else {
            $score += 5.0;
        }

        return max(5.0, min(95.0, $score));
    }

    private function normalizeScore(float $raw): float
    {
        if ($raw <= 1.0) {
            $raw *= 100.0;
        }

        return max(0.0, min(100.0, $raw));
    }

    /**
     * @return array{score: float, label: string, source: string, indicator: string}
     */
    private function decorate(float $score, string $source): array
    {
        if ($score >= 70.0) {
            return [
                'score' => round($score, 1),
                'label' => 'Risque eleve',
                'source' => $source,
                'indicator' => 'Indicateur: niveau de risque eleve, intervention RH recommandee rapidement.',
            ];
        }

        if ($score >= 40.0) {
            return [
                'score' => round($score, 1),
                'label' => 'Risque moyen',
                'source' => $source,
                'indicator' => 'Indicateur: risque moyen, suivi periodique conseille pour stabiliser la situation.',
            ];
        }

        return [
            'score' => round($score, 1),
            'label' => 'Risque faible',
            'source' => $source,
            'indicator' => 'Indicateur: profil stable, pas de signal critique a ce stade.',
        ];
    }
}
