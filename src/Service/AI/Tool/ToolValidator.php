<?php

namespace App\Service\AI\Tool;

use Symfony\Bundle\SecurityBundle\Security;
use App\Repository\Recrutement\JobOfferRepository;
use App\Repository\Recrutement\ApplicationRepository;
use Psr\Log\LoggerInterface;

class ToolValidator
{
    public function __construct(
        private Security $security,
        private JobOfferRepository $jobOfferRepository,
        private ApplicationRepository $applicationRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * Validates a tool call before execution.
     * 
     * @throws \Exception If validation fails
     */
    public function validate(string $toolName, array $args): void
    {
        $user = $this->security->getUser();
        if (!$user) {
            throw new \Exception("Authentication required for tool execution.");
        }

        $userId = method_exists($user, 'getId') ? $user->getId() : null;
        if (!$userId) {
            throw new \Exception("Cannot determine current user id for tool execution.");
        }

        // 1. RH Isolation Check (Privacy)
        if (isset($args['job_id'])) {
            $job = $this->jobOfferRepository->find($args['job_id']);
            if ($job && $job->getCreatedBy() !== $userId) {
                $this->logger->warning('AI tool validation failed: job ownership mismatch', [
                    'tool' => $toolName,
                    'job_id' => $args['job_id'],
                    'job_created_by' => $job->getCreatedBy(),
                    'user_id' => $userId,
                ]);
                throw new \Exception("Access Denied: You do not own this Job Offer.");
            }
        }

        if (isset($args['application_id'])) {
            $app = $this->applicationRepository->find($args['application_id']);
            if ($app && $app->getJobOffer() && $app->getJobOffer()->getCreatedBy() !== $userId) {
                $this->logger->warning('AI tool validation failed: application ownership mismatch', [
                    'tool' => $toolName,
                    'application_id' => $args['application_id'],
                    'job_created_by' => $app->getJobOffer()?->getCreatedBy(),
                    'user_id' => $userId,
                ]);
                throw new \Exception("Access Denied: You do not own the Job Offer associated with this application.");
            }
        }

        // 2. Business Rule: Interview Scheduling
        if (in_array($toolName, ['schedule_interview', 'reschedule_interview'])) {
            if (isset($args['date'])) {
                $date = new \DateTime($args['date']);
                $now = new \DateTime();

                if ($date < $now) {
                    throw new \Exception("Cannot schedule interviews in the past.");
                }

                $dayOfWeek = (int)$date->format('N'); // 1 (Mon) to 7 (Sun)
                if ($dayOfWeek >= 6) {
                    throw new \Exception("Interviews cannot be scheduled on weekends.");
                }

                $hour = (int)$date->format('H');
                if ($hour < 9 || $hour >= 18) {
                    throw new \Exception("Interviews must be within business hours (09:00 - 18:00).");
                }
            }
        }

        // 3. Safety Check: Destructive Actions
        if (str_contains($toolName, 'delete') || str_contains($toolName, 'reject')) {
            // In a real production app, we might log this or require a double confirmation flag
        }
    }
}
