<?php

namespace App\Controller\rh;

use App\Entity\Recrutement\Application;
use App\Form\Recrutement\ApplicationType;
use App\Repository\Recrutement\ApplicationRepository;
use App\Repository\Recrutement\InterviewRepository;
use App\Repository\Recrutement\JobOfferRepository;
use App\Security\DbUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class RecruitmentApplicationController extends AbstractController
{
    #[Route('/rh/recruitment/applications', name: 'app_rh_applications', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function index(
        Request $request,
        ApplicationRepository $applicaitonRepository,
        JobOfferRepository $jobOfferRepository
    ): Response {
        $rh = $this->getCurrentRh();
        $jobOfferId = $request->query->getInt('job_offer_id');
        $status = $request->query->get('status');
        $department = $request->query->get('department');

        $applications = $applicaitonRepository->findByRh($rh, $jobOfferId ?: null, $status, $department);
        $allOffers = $jobOfferRepository->findByRh($rh);
        $stats = [
            'totalApplications' => $applicaitonRepository->countByRh($rh),
            'pendingApplications' => $applicaitonRepository->countPending($rh),
            'statusCounts' => $applicaitonRepository->getStatusStats($rh),
        ];

        // Get current offer info if filtering by job offer
        $currentOffer = null;
        if ($jobOfferId) {
            $currentOffer = $jobOfferRepository->findOneByRh($jobOfferId, $rh);
        }

        return $this->render('Recrutement/recruitment_applications.html.twig', [
            'applications' => $applications,
            'allOffers' => $allOffers,
            'currentOffer' => $currentOffer,
            'stats' => $stats,
            'deletedApplications' => $applicaitonRepository->findDeletedByRh($rh),
        ]);
    }

    #[Route('/rh/recruitment/applications/{id}/status', name: 'app_rh_applications_status', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function updateStatus(string $id, Request $request, ApplicationRepository $applicaitonRepository, EntityManagerInterface $em): RedirectResponse
    {
        $idInt = (int) $id;
        if (!$this->isCsrfTokenValid('application_status_' . $idInt, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_applications');
        }

        $status = trim((string) $request->request->get('status', ''));
        $validStatuses = ['PENDING', 'REVIEWING', 'INTERVIEW', 'OFFER', 'HIRED', 'REJECTED'];

        if (!in_array($status, $validStatuses, true)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('app_rh_applications');
        }

        $rh = $this->getCurrentRh();
        $application = $applicaitonRepository->findOneByRh($idInt, $rh);

        if (!$application) {
            $this->addFlash('error', 'Candidature non trouvée.');
            return $this->redirectToRoute('app_rh_applications');
        }

        $application->setStatus($status);
        $em->flush();

        $this->addFlash('success', 'Statut mis à jour avec succès.');
        return $this->redirectToRoute('app_rh_applications');
    }

    #[Route('/rh/recruitment/applications/{id}/edit', name: 'app_rh_applications_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RH')]
    public function edit(string $id, Request $request, ApplicationRepository $applicaitonRepository, JobOfferRepository $jobOfferRepository, EntityManagerInterface $em): Response
    {
        $idInt = (int) $id;
        $rh = $this->getCurrentRh();
        $application = $applicaitonRepository->findOneByRh($idInt, $rh);

        if (!$application) {
            $this->addFlash('error', 'Candidature non trouvée.');
            return $this->redirectToRoute('app_rh_applications');
        }

        $form = $this->createForm(ApplicationType::class, $application, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Candidature mise à jour avec succès.');
            return $this->redirectToRoute('app_rh_applications');
        }

        return $this->render('Recrutement/application_edit.html.twig', [
            'application' => $application,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/rh/recruitment/applications/{id}/delete', name: 'app_rh_applications_delete', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function delete(string $id, Request $request, ApplicationRepository $applicaitonRepository, EntityManagerInterface $em): RedirectResponse
    {
        $idInt = (int) $id;
        if (!$this->isCsrfTokenValid('delete_application_' . $idInt, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_applications');
        }

        $rh = $this->getCurrentRh();
        $application = $applicaitonRepository->findOneByRh($idInt, $rh);

        if (!$application) {
            $this->addFlash('error', 'Candidature non trouvée.');
            return $this->redirectToRoute('app_rh_applications');
        }

        // Soft delete
        $application->setIsDeleted(true);
        $em->flush();

        $this->addFlash('success', 'Candidature supprimée avec succès.');
        return $this->redirectToRoute('app_rh_applications');
    }

    #[Route('/rh/recruitment/applications/{id}/restore', name: 'app_rh_applications_restore', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function restore(string $id, Request $request, ApplicationRepository $applicaitonRepository, EntityManagerInterface $em): RedirectResponse
    {
        $idInt = (int) $id;
        if (!$this->isCsrfTokenValid('restore_application_' . $idInt, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_applications');
        }

        $rh = $this->getCurrentRh();
        $application = $applicaitonRepository->findOneByRhIncludingDeleted($idInt, $rh);

        if (!$application) {
            $this->addFlash('error', 'Candidature non trouvée.');
            return $this->redirectToRoute('app_rh_applications');
        }

        $application->setIsDeleted(false);
        $em->flush();

        $this->addFlash('success', 'Candidature restaurée avec succès.');
        return $this->redirectToRoute('app_rh_applications');
    }

    #[Route('/rh/recruitment/applications/{id}/delete-permanent', name: 'app_rh_applications_delete_permanent', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function deletePermanent(string $id, Request $request, ApplicationRepository $applicaitonRepository, EntityManagerInterface $em): RedirectResponse
    {
        $idInt = (int) $id;
        if (!$this->isCsrfTokenValid('permanent_delete_application_' . $idInt, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_applications');
        }

        $rh = $this->getCurrentRh();
        $application = $applicaitonRepository->findOneByRhIncludingDeleted($idInt, $rh);

        if (!$application) {
            $this->addFlash('error', 'Candidature non trouvée.');
            return $this->redirectToRoute('app_rh_applications');
        }

        $em->remove($application);
        $em->flush();

        $this->addFlash('success', 'Candidature définitivement supprimée.');
        return $this->redirectToRoute('app_rh_applications');
    }

    #[Route('/rh/recruitment/applications/{id}', name: 'app_rh_applications_show', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function show(string $id, ApplicationRepository $applicaitonRepository, InterviewRepository $interviewRepository): Response
    {
        $idInt = (int) $id;
        $rh = $this->getCurrentRh();
        $application = $applicaitonRepository->findOneByRhIncludingDeleted($idInt, $rh);

        if (!$application) {
            $this->addFlash('error', 'Candidature non trouvée.');
            return $this->redirectToRoute('app_rh_applications');
        }

        // Get interviews for this application
        $interviews = $interviewRepository->findByApplication($application->getId());

        return $this->render('Recrutement/application_details.html.twig', [
            'application' => $application,
            'interviews' => $interviews,
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
