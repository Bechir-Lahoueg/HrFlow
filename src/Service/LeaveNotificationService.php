<?php

namespace App\Service;

use App\Entity\Rh\LeaveNotification;
use App\Entity\Rh\LeaveRequest;
use App\Repository\Rh\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class LeaveNotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * Employee submits a leave request → notify the RH user.
     */
    public function notifyRhNewRequest(LeaveRequest $leave): void
    {
        $employee = $leave->getEmployee();
        if (!$employee) {
            return;
        }

        $rhUser = $this->userRepository->find($employee->getRhId());
        if (!$rhUser) {
            return;
        }

        $isException = $leave->getRequestCategory() === 'EXCEPTION';
        $typeLabel = $isException ? 'exceptionnelle' : 'de conge';

        $notification = new LeaveNotification();
        $notification->setRecipientType(LeaveNotification::RECIPIENT_USER)
            ->setRecipientId($rhUser->getId())
            ->setLeaveRequestId($leave->getId())
            ->setType(LeaveNotification::TYPE_LEAVE_SUBMITTED)
            ->setTitle('Nouvelle demande ' . $typeLabel)
            ->setMessage(sprintf(
                '%s a soumis une demande %s du %s au %s (%d jours).',
                $employee->getFullName(),
                $typeLabel,
                $leave->getStartDate()->format('d/m/Y'),
                $leave->getEndDate()->format('d/m/Y'),
                $leave->getDaysCount()
            ));

        $this->em->persist($notification);
        $this->em->flush();
    }

    /**
     * RH pre-approves an exception request → notify all Admin users.
     */
    public function notifyAdminExceptionPending(LeaveRequest $leave): void
    {
        $employee = $leave->getEmployee();
        if (!$employee) {
            return;
        }

        $admins = $this->userRepository->findBy(['role' => 'ADMIN']);

        foreach ($admins as $admin) {
            $notification = new LeaveNotification();
            $notification->setRecipientType(LeaveNotification::RECIPIENT_USER)
                ->setRecipientId($admin->getId())
                ->setLeaveRequestId($leave->getId())
                ->setType(LeaveNotification::TYPE_EXCEPTION_PENDING_ADMIN)
                ->setTitle('Demande exceptionnelle a valider')
                ->setMessage(sprintf(
                    'Demande exceptionnelle de %s (pre-approuvee par RH). Du %s au %s (%d jours). Urgence: %s. Motif: %s',
                    $employee->getFullName(),
                    $leave->getStartDate()->format('d/m/Y'),
                    $leave->getEndDate()->format('d/m/Y'),
                    $leave->getDaysCount(),
                    $leave->getUrgencyLevel() ?? 'N/A',
                    mb_substr($leave->getReason(), 0, 100)
                ));

            $this->em->persist($notification);
        }

        $this->em->flush();
    }

    /**
     * Leave request approved (by RH or Admin) → notify the employee.
     */
    public function notifyEmployeeApproved(LeaveRequest $leave): void
    {
        $employee = $leave->getEmployee();
        if (!$employee) {
            return;
        }

        $isException = $leave->getRequestCategory() === 'EXCEPTION';

        $notification = new LeaveNotification();
        $notification->setRecipientType(LeaveNotification::RECIPIENT_EMPLOYEE)
            ->setRecipientId($employee->getId())
            ->setLeaveRequestId($leave->getId())
            ->setType($isException ? LeaveNotification::TYPE_EXCEPTION_APPROVED : LeaveNotification::TYPE_LEAVE_APPROVED)
            ->setTitle('Demande de conge acceptee')
            ->setMessage(sprintf(
                'Votre demande %s du %s au %s (%d jours) a ete acceptee.',
                $isException ? 'exceptionnelle' : 'de conge',
                $leave->getStartDate()->format('d/m/Y'),
                $leave->getEndDate()->format('d/m/Y'),
                $leave->getDaysCount()
            ));

        $this->em->persist($notification);
        $this->em->flush();
    }

    /**
     * Leave request rejected (by RH or Admin) → notify the employee.
     */
    public function notifyEmployeeRejected(LeaveRequest $leave, string $comment): void
    {
        $employee = $leave->getEmployee();
        if (!$employee) {
            return;
        }

        $isException = $leave->getRequestCategory() === 'EXCEPTION';

        $notification = new LeaveNotification();
        $notification->setRecipientType(LeaveNotification::RECIPIENT_EMPLOYEE)
            ->setRecipientId($employee->getId())
            ->setLeaveRequestId($leave->getId())
            ->setType($isException ? LeaveNotification::TYPE_EXCEPTION_REJECTED : LeaveNotification::TYPE_LEAVE_REJECTED)
            ->setTitle('Demande de conge refusee')
            ->setMessage(sprintf(
                'Votre demande %s du %s au %s a ete refusee. Commentaire: %s',
                $isException ? 'exceptionnelle' : 'de conge',
                $leave->getStartDate()->format('d/m/Y'),
                $leave->getEndDate()->format('d/m/Y'),
                mb_substr($comment, 0, 150)
            ));

        $this->em->persist($notification);
        $this->em->flush();
    }
}
