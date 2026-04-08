<?php

namespace App\Controller\rh;

use App\Entity\Interview;
use App\Form\InterviewType;
use App\Repository\ApplicationRepository;
use App\Repository\InterviewRepository;
use App\Repository\UserRepository;
use App\Security\DbUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class RecruitmentInterviewController extends AbstractController
{
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
    public function create(Request $request, ApplicationRepository $applicaitonRepository, EntityManagerInterface $em, UserRepository $userRepository): RedirectResponse
    {
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
            $em->persist($interview);
            $em->flush();

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

        if ($score < 0 || $score > 10) {
            $this->addFlash('error', 'Le score doit être entre 0 et 10.');
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

        $this->addFlash('success', 'Évaluation enregistrée avec succès.');
        return $this->redirectToRoute('app_rh_interviews');
    }

    #[Route('/rh/recruitment/interviews/{id}/edit', name: 'app_rh_interviews_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RH')]
    public function edit(int $id, Request $request, InterviewRepository $interviewRepository, ApplicationRepository $applicaitonRepository, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
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
            $em->flush();
            $this->addFlash('success', 'Entretien mis à jour avec succès.');
            return $this->redirectToRoute('app_rh_interviews');
        }

        return $this->render('Recrutement/interview_edit.html.twig', [
            'interview' => $interview,
            'form' => $form->createView(),
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
