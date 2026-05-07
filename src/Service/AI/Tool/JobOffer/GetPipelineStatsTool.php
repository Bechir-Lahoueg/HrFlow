<?php

namespace App\Service\AI\Tool\JobOffer;

use App\Service\AI\Tool\ToolInterface;
use App\Repository\Recrutement\ApplicationRepository;
use App\Security\DbUser;
use Symfony\Bundle\SecurityBundle\Security;

class GetPipelineStatsTool implements ToolInterface
{
    public function __construct(
        private ApplicationRepository $applicationRepository,
        private Security $security
    ) {}

    public function getName(): string
    {
        return 'get_pipeline_stats';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'get_pipeline_stats',
            'description' => 'Retrieves recruitment pipeline statistics for the current user.',
            'parameters' => [
                'type' => 'object',
                'properties' => (object)[],
                'additionalProperties' => false
            ]
        ];
    }

    public function execute(array $args): mixed
    {
        $user = $this->security->getUser();
        if (!$user instanceof DbUser) {
            return ['error' => 'Not authenticated'];
        }
        $stats = $this->applicationRepository->getStatusStats($user);

        $total = array_sum($stats);
        
        return [
            'funnel' => $stats,
            'total_applications' => $total,
            'insights' => $this->generateInsights($stats, $total)
        ];
    }

    /** @param array<mixed> $stats */
    private function generateInsights(array $stats, int $total): string
    {
        if ($total === 0) return "No candidates found in your pipeline yet.";

        $hired = $stats['HIRED'] ?? 0;
        $conversion = round(($hired / $total) * 100, 1);

        if ($conversion > 20) {
            return "Excellent hiring rate ({$conversion}%).";
        } elseif (($stats['PENDING'] ?? 0) > ($total * 0.5)) {
            return "Pipeline bottleneck: Many candidates are stuck in PENDING status.";
        }

        return "Overall conversion rate is {$conversion}%.";
    }
}
