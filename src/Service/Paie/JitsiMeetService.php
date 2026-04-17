<?php

namespace App\Service\Paie;

use App\Entity\Rh\Employee;

/**
 * JitsiMeetService — Generates Jitsi meeting links and determines
 * whether a meeting should be suggested for an employee.
 */
final class JitsiMeetService
{
    private const JITSI_BASE_URL = 'https://meet.jit.si/';
    private const HIGH_DEDUCTION_THRESHOLD = 3;  // number of deductions
    private const RECENT_MONTHS = 3;             // within N months

    /**
     * Generate a Jitsi meeting link for a given employee.
     */
    public function generateMeetingLink(Employee $employee, ?string $suffix = null): string
    {
        $roomName = sprintf(
            'hrflow-%d-%s%s',
            $employee->getId(),
            date('Ymd'),
            $suffix ? '-' . $suffix : ''
        );

        // Sanitize room name (only alphanumeric + dash)
        $roomName = preg_replace('/[^a-zA-Z0-9\-]/', '', $roomName);

        return self::JITSI_BASE_URL . $roomName;
    }

    /**
     * Determine if a meeting should be suggested based on recent deduction count.
     */
    public function shouldSuggestMeeting(int $recentDeductionCount): bool
    {
        return $recentDeductionCount >= self::HIGH_DEDUCTION_THRESHOLD;
    }

    public function getThreshold(): int
    {
        return self::HIGH_DEDUCTION_THRESHOLD;
    }

    public function getRecentMonths(): int
    {
        return self::RECENT_MONTHS;
    }
}
