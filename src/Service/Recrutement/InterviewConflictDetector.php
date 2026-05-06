<?php

namespace App\Service\Recrutement;

use App\Entity\Recrutement\Interview;
use App\Repository\Recrutement\InterviewRepository;

class InterviewConflictDetector
{
    private InterviewRepository $interviewRepository;

    public function __construct(InterviewRepository $interviewRepository)
    {
        $this->interviewRepository = $interviewRepository;
    }

    /**
     * Check for conflicts when scheduling an interview
     *
     * @return array{hasConflict: bool, conflicts: Interview[], message: string}
     */
    public function checkConflicts(
        int $interviewerId,
        \DateTimeInterface $interviewDate,
        ?int $excludeInterviewId = null,
        int $bufferMinutes = 60
    ): array {
        $conflicts = $this->interviewRepository->findConflictingInterviews(
            $interviewerId,
            $interviewDate,
            $excludeInterviewId,
            $bufferMinutes
        );

        $hasConflict = count($conflicts) > 0;
        $message = '';

        if ($hasConflict) {
            $conflict = $conflicts[0];
            $message = sprintf(
                'Conflit détecté : L\'interviewer a déjà un entretien programmé à %s avec %s',
                $conflict->getInterviewDate()?->format('H:i') ?? '?',
                $conflict->getApplication()?->getCandidateName() ?? 'un candidat'
            );
        }

        return [
            'hasConflict' => $hasConflict,
            'conflicts' => $conflicts,
            'message' => $message,
        ];
    }

    /**
     * Get available time slots for an interviewer on a specific date
     *
     * @return array<int, array{time: string, available: bool}>
     */
    public function getAvailableTimeSlots(
        int $interviewerId,
        \DateTimeInterface $date,
        string $startHour = '09:00',
        string $endHour = '18:00',
        int $slotDurationMinutes = 60
    ): array {
        $schedule = $this->interviewRepository->findInterviewerScheduleForDate($interviewerId, $date);
        $bookedSlots = [];

        foreach ($schedule as $interview) {
            $bookedSlots[] = $interview->getInterviewDate()?->format('H:i') ?? '';
        }

        $slots = [];
        $current = \DateTime::createFromFormat('H:i', $startHour);
        $end = \DateTime::createFromFormat('H:i', $endHour);

        if ($current === false || $end === false) {
            return [];
        }

        while ($current < $end) {
            $timeSlot = $current->format('H:i');
            $slots[] = [
                'time' => $timeSlot,
                'available' => !in_array($timeSlot, $bookedSlots, true),
            ];
            $current->modify("+{$slotDurationMinutes} minutes");
        }

        return $slots;
    }

    /**
     * Validate interview can be scheduled
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function validateInterviewScheduling(
        int $interviewerId,
        \DateTimeInterface $interviewDate,
        ?int $excludeInterviewId = null
    ): array {
        $errors = [];

        // Check if date is in the past
        $now = new \DateTime();
        if ($interviewDate < $now) {
            $errors[] = 'La date de l\'entretien ne peut pas être dans le passé.';
        }

        // Check for conflicts
        $conflictCheck = $this->checkConflicts($interviewerId, $interviewDate, $excludeInterviewId);
        if ($conflictCheck['hasConflict']) {
            $errors[] = $conflictCheck['message'];
        }

        // Check business hours (9 AM to 6 PM)
        $hour = (int) $interviewDate->format('H');
        if ($hour < 9 || $hour >= 18) {
            $errors[] = 'Les entretiens doivent être programmés entre 09:00 et 18:00.';
        }

        // Check weekends
        $dayOfWeek = (int) $interviewDate->format('N');
        if ($dayOfWeek >= 6) {
            $errors[] = 'Les entretiens ne peuvent pas être programmés le week-end.';
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
        ];
    }
}
