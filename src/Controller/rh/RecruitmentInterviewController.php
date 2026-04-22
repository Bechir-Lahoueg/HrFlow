<?php

namespace App\Controller\rh;

use App\Entity\Recrutement\Interview;
use App\Form\Recrutement\InterviewType;
use App\Repository\Recrutement\ApplicationRepository;
use App\Repository\Recrutement\InterviewRepository;
use App\Repository\Rh\UserRepository;
use App\Security\DbUser;
use App\Service\Recrutement\InterviewConflictDetector;
use App\Service\Recrutement\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class RecruitmentInterviewController extends AbstractController
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {
    }
    #[Route('/rh/recruitment/interviews', name: 'app_rh_interviews', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function index(
        Request $request,
        InterviewRepository $interviewRepository,
        ApplicationRepository $applicaitonRepository,
        UserRepository $userRepository
    ): Response {
        $rh = $this->getCurrentRh();
        $applicationId = $request->query->getInt('application_id');

        $interviews = $interviewRepository->findByRh($rh, $applicationId ?: null);
        $allApplications = $applicaitonRepository->findByRh($rh);
        
        $interviewers = $userRepository->findInterviewers();

        $interviewerChoices = [];
        foreach ($interviewers as $user) {
            $label = $user->getEmail() ? sprintf('%s (%s)', $user->getUsername(), $user->getEmail()) : $user->getUsername();
            $interviewerChoices[$label] = $user->getId();
        }

        // Get current application info if filtering
        $currentApplication = null;
        if ($applicationId) {
            $currentApplication = $applicaitonRepository->findOneByRh($applicationId, $rh);
        }

        // Create form for new interview
        $interview = new Interview();
        $form = $this->createForm(InterviewType::class, $interview, [
            'is_edit' => false,
            'interviewer_choices' => $interviewerChoices,
            'rh_id' => $rh->getId(),
        ]);

        // Get interview stats
        $stats = [
            'totalInterviews' => $interviewRepository->countByRh($rh),
            'upcomingInterviews' => $interviewRepository->countUpcoming($rh),
            'resultCounts' => $interviewRepository->getResultStats($rh),
            'averageScore' => $interviewRepository->getAverageScore($rh),
        ];

        return $this->render('Recrutement/recruitment_interviews.html.twig', [
            'interviews' => $interviews,
            'allApplications' => $allApplications,
            'interviewers' => $interviewers,
            'currentApplication' => $currentApplication,
            'form' => $form->createView(),
            'stats' => $stats,
            'deletedInterviews' => $interviewRepository->findDeletedByRh($rh),
        ]);
    }

    #[Route('/rh/recruitment/interviews/create', name: 'app_rh_interviews_create', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function create(
        Request $request,
        ApplicationRepository $applicaitonRepository,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        InterviewConflictDetector $conflictDetector
    ): RedirectResponse {
        $rh = $this->getCurrentRh();

        $interviewers = $userRepository->findInterviewers();

        $interviewerChoices = [];
        foreach ($interviewers as $user) {
            $label = $user->getEmail() ? sprintf('%s (%s)', $user->getUsername(), $user->getEmail()) : $user->getUsername();
            $interviewerChoices[$label] = $user->getId();
        }
        
        $interview = new Interview();
        $form = $this->createForm(InterviewType::class, $interview, [
            'is_edit' => false,
            'interviewer_choices' => $interviewerChoices,
            'rh_id' => $rh->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check for conflicts
            $validation = $conflictDetector->validateInterviewScheduling(
                $interview->getInterviewerId(),
                $interview->getInterviewDate()
            );

            if (!$validation['valid']) {
                foreach ($validation['errors'] as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->redirectToRoute('app_rh_interviews');
            }

            $em->persist($interview);
            $em->flush();

            // Send notification to candidate
            try {
                $this->notificationService->sendInterviewScheduledEmail($interview);
            } catch (\Exception $e) {
                // Log error but don't fail the request
            }

            $this->addFlash('success', 'Entretien planifié avec succès.');
            return $this->redirectToRoute('app_rh_interviews');
        }

        // If form has errors, flash them
        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_rh_interviews');
    }

    #[Route('/rh/recruitment/interviews/{id}/evaluate', name: 'app_rh_interviews_evaluate', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function evaluate(int $id, Request $request, InterviewRepository $interviewRepository, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('evaluate_interview_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_interviews');
        }

        $result = trim((string) $request->request->get('result', ''));
        $score = (int) $request->request->get('score', 0);
        $feedback = trim((string) $request->request->get('feedback', ''));

        $validResults = ['PENDING', 'PASSED', 'FAILED', 'NO_SHOW'];

        if (!in_array($result, $validResults, true)) {
            $this->addFlash('error', 'Résultat invalide.');
            return $this->redirectToRoute('app_rh_interviews');
        }

        if ($score < 0 || $score > 100) {
            $this->addFlash('error', 'Le score doit être entre 0 et 100.');
            return $this->redirectToRoute('app_rh_interviews');
        }

        // Check minimum score requirement: if PASSED, score must be >= 60
        if ($result === 'PASSED' && $score < 60) {
            $this->addFlash('error', 'Le score minimum pour réussir un entretien est 60/100.');
            return $this->redirectToRoute('app_rh_interviews');
        }

        // If score is < 60, can't be marked as PASSED
        if ($score > 0 && $score < 60 && $result === 'PASSED') {
            $this->addFlash('error', 'Un candidat avec un score de ' . $score . '/100 ne peut pas être marqué comme réussi.');
            return $this->redirectToRoute('app_rh_interviews');
        }

        $rh = $this->getCurrentRh();
        $interview = $interviewRepository->findOneByRh($id, $rh);

        if (!$interview) {
            $this->addFlash('error', 'Entretien non trouvé.');
            return $this->redirectToRoute('app_rh_interviews');
        }

        $interview->setResult($result);
        $interview->setScore($score);
        $interview->setFeedback($feedback ?: null);
        $em->flush();

        // Send notification to candidate about the result
        try {
            $this->notificationService->sendInterviewResultEmail($interview);
        } catch (\Exception $e) {
            // Log error but don't fail the request
        }

        $this->addFlash('success', 'Évaluation enregistrée avec succès.');
        return $this->redirectToRoute('app_rh_interviews');
    }

    #[Route('/rh/recruitment/interviews/{id}/edit', name: 'app_rh_interviews_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RH')]
    public function edit(
        int $id,
        Request $request,
        InterviewRepository $interviewRepository,
        ApplicationRepository $applicaitonRepository,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        InterviewConflictDetector $conflictDetector
    ): Response {
        $rh = $this->getCurrentRh();
        $interview = $interviewRepository->findOneByRh($id, $rh);

        if (!$interview) {
            $this->addFlash('error', 'Entretien non trouvé.');
            return $this->redirectToRoute('app_rh_interviews');
        }

        $interviewers = $userRepository->findInterviewers();
        $interviewerChoices = [];
        foreach ($interviewers as $user) {
            $label = $user->getEmail() ? sprintf('%s (%s)', $user->getUsername(), $user->getEmail()) : $user->getUsername();
            $interviewerChoices[$label] = $user->getId();
        }

        $form = $this->createForm(InterviewType::class, $interview, [
            'is_edit' => true,
            'interviewer_choices' => $interviewerChoices,
            'rh_id' => $rh->getId(),
            'current_application_id' => $interview->getApplication()?->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check for conflicts (excluding current interview)
            $validation = $conflictDetector->validateInterviewScheduling(
                $interview->getInterviewerId(),
                $interview->getInterviewDate(),
                $interview->getId()
            );

            if (!$validation['valid']) {
                foreach ($validation['errors'] as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->redirectToRoute('app_rh_interviews_edit', ['id' => $id]);
            }

            $em->flush();
            $this->addFlash('success', 'Entretien mis à jour avec succès.');
            return $this->redirectToRoute('app_rh_interviews');
        }

        return $this->render('Recrutement/interview_edit.html.twig', [
            'interview' => $interview,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/rh/recruitment/interviews/check-availability', name: 'app_rh_interviews_check_availability', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function checkAvailability(
        Request $request,
        InterviewConflictDetector $conflictDetector
    ): JsonResponse {
        $interviewerId = $request->query->getInt('interviewer_id');
        $date = $request->query->get('date');
        $excludeId = $request->query->getInt('exclude_id');

        if (!$interviewerId || !$date) {
            return $this->json(['error' => 'Missing parameters'], 400);
        }

        try {
            $interviewDate = new \DateTime($date);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid date format'], 400);
        }

        $conflictCheck = $conflictDetector->checkConflicts(
            $interviewerId,
            $interviewDate,
            $excludeId > 0 ? $excludeId : null
        );

        $validation = $conflictDetector->validateInterviewScheduling(
            $interviewerId,
            $interviewDate,
            $excludeId > 0 ? $excludeId : null
        );

        return $this->json([
            'hasConflict' => $conflictCheck['hasConflict'],
            'message' => $conflictCheck['message'],
            'errors' => $validation['errors'],
            'valid' => $validation['valid'],
        ]);
    }

    #[Route('/rh/recruitment/interviews/availability-slots', name: 'app_rh_interviews_availability_slots', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function getAvailabilitySlots(
        Request $request,
        InterviewConflictDetector $conflictDetector
    ): JsonResponse {
        $interviewerId = $request->query->getInt('interviewer_id');
        $date = $request->query->get('date');

        if (!$interviewerId || !$date) {
            return $this->json(['error' => 'Missing parameters'], 400);
        }

        try {
            $slotDate = new \DateTime($date);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid date format'], 400);
        }

        $slots = $conflictDetector->getAvailableTimeSlots($interviewerId, $slotDate);

        return $this->json([
            'slots' => $slots,
            'date' => $slotDate->format('Y-m-d'),
        ]);
    }

    #[Route('/rh/recruitment/interviews/{id}/delete', name: 'app_rh_interviews_delete', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function delete(int $id, Request $request, InterviewRepository $interviewRepository, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('delete_interview_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_interviews');
        }

        $rh = $this->getCurrentRh();
        $interview = $interviewRepository->findOneByRh($id, $rh);

        if (!$interview) {
            $this->addFlash('error', 'Entretien non trouvé.');
            return $this->redirectToRoute('app_rh_interviews');
        }

        // Soft delete
        $interview->setIsDeleted(true);
        $em->flush();

        $this->addFlash('success', 'Entretien supprimé avec succès.');
        return $this->redirectToRoute('app_rh_interviews');
    }

    #[Route('/rh/recruitment/interviews/{id}/restore', name: 'app_rh_interviews_restore', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function restore(int $id, Request $request, InterviewRepository $interviewRepository, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('restore_interview_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_interviews');
        }

        $rh = $this->getCurrentRh();
        $interview = $interviewRepository->findOneByRhIncludingDeleted($id, $rh);

        if (!$interview) {
            $this->addFlash('error', 'Entretien non trouvé.');
            return $this->redirectToRoute('app_rh_interviews');
        }

        $interview->setIsDeleted(false);
        $em->flush();

        $this->addFlash('success', 'Entretien restauré avec succès.');
        return $this->redirectToRoute('app_rh_interviews');
    }

    #[Route('/rh/recruitment/interviews/{id}/delete-permanent', name: 'app_rh_interviews_delete_permanent', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function deletePermanent(int $id, Request $request, InterviewRepository $interviewRepository, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('permanent_delete_interview_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_interviews');
        }

        $rh = $this->getCurrentRh();
        $interview = $interviewRepository->findOneByRhIncludingDeleted($id, $rh);

        if (!$interview) {
            $this->addFlash('error', 'Entretien non trouvé.');
            return $this->redirectToRoute('app_rh_interviews');
        }

        $em->remove($interview);
        $em->flush();

        $this->addFlash('success', 'Entretien définitivement supprimé.');
        return $this->redirectToRoute('app_rh_interviews');
    }

    #[Route('/rh/recruitment/interviews/calendar', name: 'app_rh_interviews_calendar', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function calendar(InterviewRepository $interviewRepository): Response
    {
        $rh = $this->getCurrentRh();
        $interviews = $interviewRepository->findByRh($rh);
        
        $events = [];
        foreach ($interviews as $interview) {
            $events[] = [
                'id' => $interview->getId(),
                'title' => sprintf('%s - %s', $interview->getApplication()?->getCandidateName(), $interview->getType()),
                'start' => $interview->getInterviewDate()->format('Y-m-d\TH:i:s'),
                'url' => $this->generateUrl('app_rh_interviews_show', ['id' => $interview->getId()]),
                'backgroundColor' => match($interview->getResult()) {
                    'PASSED' => '#10b981',
                    'FAILED' => '#ef4444',
                    'PENDING' => '#6366f1',
                    default => '#94a3b8'
                }
            ];
        }

        return $this->render('Recrutement/recruitment_calendar.html.twig', [
            'events' => json_encode($events)
        ]);
    }

    #[Route('/rh/recruitment/interviews/{id}', name: 'app_rh_interviews_show', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function show(int $id, InterviewRepository $interviewRepository, UserRepository $userRepository): Response
    {
        $rh = $this->getCurrentRh();
        $interview = $interviewRepository->findOneByRhIncludingDeleted($id, $rh);

        if (!$interview) {
            $this->addFlash('error', 'Entretien non trouvé.');
            return $this->redirectToRoute('app_rh_interviews');
        }

        $interviewer = $interview->getInterviewerId()
            ? $userRepository->find($interview->getInterviewerId())
            : null;

        return $this->render('Recrutement/interview_details.html.twig', [
            'interview' => $interview,
            'interviewer' => $interviewer,
        ]);
    }

    private function getCurrentRh(): DbUser
    {
        $user = $this->getUser();

        if (!$user instanceof DbUser) {
            throw $this->createAccessDeniedException('Utilisateur RH invalide.');
        }

        return $user;
    }
}
