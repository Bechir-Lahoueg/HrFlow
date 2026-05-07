<?php

namespace App\Controller\rh;

use App\Entity\Recrutement\Application;
use App\Form\CandidateApplicationType;
use App\Form\Recrutement\BulkApplicationActionType;
use App\Repository\Recrutement\ApplicationRepository;
use App\Repository\Recrutement\InterviewRepository;
use App\Repository\Recrutement\JobOfferRepository;
use App\Security\DbUser;
use App\Service\Recrutement\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class RecruitmentApplicationController extends AbstractController
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {
    }
    #[Route('/rh/recruitment/applications', name: 'app_rh_applications', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RH')]
    public function index(
        Request $request,
        ApplicationRepository $applicationRepository,
        JobOfferRepository $jobOfferRepository,
        PaginatorInterface $paginator,
        EntityManagerInterface $em
    ): Response {
        $rh = $this->getCurrentRh();
        $jobOfferId = $request->query->getInt('job_offer_id');
        $status = $request->query->get('status');
        $department = $request->query->get('department');
        $search = $request->query->get('search');

        $query = $applicationRepository->findByRhQuery($rh, $jobOfferId ?: null, $status, $department, $search);
        
        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            20 // items per page
        );
        
        $allOffers = $jobOfferRepository->findByRh($rh);
        $stats = [
            'totalApplications' => $applicationRepository->countByRh($rh),
            'pendingApplications' => $applicationRepository->countPending($rh),
            'statusCounts' => $applicationRepository->getStatusStats($rh),
        ];

        // Get current offer info if filtering by job offer
        $currentOffer = null;
        if ($jobOfferId) {
            $currentOffer = $jobOfferRepository->findOneByRh($jobOfferId, $rh);
        }

        // Use current page items as bulk-form choices (eliminates duplicate full-table query)
        $applications = iterator_to_array($pagination);

        // Create bulk action form
        $bulkForm = $this->createForm(BulkApplicationActionType::class, null, [
            'applications' => $applications,
        ]);
        $bulkForm->handleRequest($request);
        
        // Handle bulk form submission
        if ($bulkForm->isSubmitted() && $bulkForm->isValid()) {
            $selected = $bulkForm->get('selected')->getData();
            $action = $bulkForm->get('action')->getData();
            
            if (empty($selected)) {
                $this->addFlash('error', 'Aucune candidature sélectionnée.');
                return $this->redirectToRoute('app_rh_applications');
            }
            
            $candidateAppsByCandidate = [];
            if ($action === 'hire') {
                $candidateIds = array_filter(array_map(fn($a) => $a->getCandidate()?->getId(), $selected));
                if (!empty($candidateIds)) {
                    $allCandidateApps = $applicationRepository->findBy(['candidate' => array_values($candidateIds)]);
                    foreach ($allCandidateApps as $ca) {
                        $cid = $ca->getCandidate()?->getId();
                        if ($cid) {
                            $candidateAppsByCandidate[$cid][] = $ca;
                        }
                    }
                }
            }

            $count = 0;
            $hiredCandidates = [];
            $hiredApplicationIds = [];
            foreach ($selected as $application) {
                switch ($action) {
                    case 'review':
                        $application->setStatus('REVIEWING');
                        $count++;
                        break;
                    case 'interview':
                        $application->setStatus('INTERVIEW');
                        $count++;
                        break;
                    case 'offer':
                        $application->setStatus('OFFER');
                        $count++;
                        break;
                    case 'hire':
                        $application->setStatus('HIRED');
                        $count++;
                        if ($application->getCandidate()) {
                            $hiredCandidates[] = $application->getCandidate();
                            $hiredApplicationIds[] = $application->getId();
                        }
                        break;
                    case 'reject':
                        $application->setStatus('REJECTED');
                        $count++;
                        break;
                }
            }

            $em->flush();

            // Batch-reject other open applications for all hired candidates (1 UPDATE query)
            if (!empty($hiredCandidates)) {
                $applicationRepository->batchRejectByCandidates($hiredCandidates, $hiredApplicationIds);
            }
            
            $actionLabels = [
                'review' => 'passées en revue',
                'interview' => 'passées en entretien',
                'offer' => 'avec offre envoyée',
                'hire' => 'recrutées',
                'reject' => 'rejetées',
            ];
            
            $this->addFlash('success', sprintf('%d candidature(s) %s avec succès.', $count, $actionLabels[$action] ?? $action));
            return $this->redirectToRoute('app_rh_applications');
        }
        
        return $this->render('Recrutement/recruitment_applications.html.twig', [
            'pagination' => $pagination,
            'allOffers' => $allOffers,
            'currentOffer' => $currentOffer,
            'stats' => $stats,
            'deletedApplications' => $applicationRepository->findDeletedByRh($rh),
            'bulkForm' => $bulkForm->createView(),
        ]);
    }

    #[Route('/rh/recruitment/applications/{id}/status', name: 'app_rh_applications_status', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function updateStatus(
        int $id,
        Request $request,
        ApplicationRepository $applicationRepository,
        EntityManagerInterface $em
    ): RedirectResponse {


        if (!$this->isCsrfTokenValid('application_status_' . $id, (string) $request->request->get('_token', ''))) {
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
        $application = $applicationRepository->findOneByRh($id, $rh);

        if (!$application) {
            $this->addFlash('error', 'Candidature non trouvée.');
            return $this->redirectToRoute('app_rh_applications');
        }

        $previousStatus = $application->getStatus();

        if ($previousStatus !== $status) {
            $application->setStatus($status);
            $em->flush();
            $this->addFlash('success', sprintf('Statut mis à jour avec succès (%s → %s).', $previousStatus, $status));
            
            // Send notification to candidate
            try {
                $this->notificationService->sendStatusUpdateEmail($application);
            } catch (\Exception $e) {
                // Log error but don't fail the request
            }
            
            // If status changed to INTERVIEW, redirect to create interview form
            if ($status === 'INTERVIEW') {
                return $this->redirectToRoute('app_rh_interviews', ['application_id' => $application->getId()]);
            }
            
            // If status changed to HIRED, reject all other applications from this candidate
            if ($status === 'HIRED' && $application->getCandidate()) {
                $allCandidateApplications = $applicationRepository->findBy(['candidate' => $application->getCandidate()]);
                foreach ($allCandidateApplications as $otherApp) {
                    if ($otherApp->getId() !== $application->getId() && $otherApp->getStatus() !== 'REJECTED') {
                        $otherApp->setStatus('REJECTED');
                    }
                }
                $em->flush();
                $this->addFlash('info', 'Les autres candidatures du candidat ont été automatiquement rejetées.');
            }
        } else {
            $this->addFlash('info', 'Le statut n\'a pas changé.');
        }

        return $this->redirectToRoute('app_rh_applications');
    }

    #[Route('/rh/recruitment/applications/{id}/edit', name: 'app_rh_applications_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RH')]
    public function edit(int $id, Request $request, ApplicationRepository $applicationRepository, JobOfferRepository $jobOfferRepository, EntityManagerInterface $em): Response
    {
        $rh = $this->getCurrentRh();
        $application = $applicationRepository->findOneByRh($id, $rh);

        if (!$application) {
            $this->addFlash('error', 'Candidature non trouvée.');
            return $this->redirectToRoute('app_rh_applications');
        }

        $form = $this->createForm(CandidateApplicationType::class, $application, ['is_edit' => true]);
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
    public function delete(int $id, Request $request, ApplicationRepository $applicationRepository, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('delete_application_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_applications');
        }

        $rh = $this->getCurrentRh();
        $application = $applicationRepository->findOneByRh($id, $rh);

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
    public function restore(int $id, Request $request, ApplicationRepository $applicationRepository, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('restore_application_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_applications');
        }

        $rh = $this->getCurrentRh();
        $application = $applicationRepository->findOneByRhIncludingDeleted($id, $rh);

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
    public function deletePermanent(int $id, Request $request, ApplicationRepository $applicationRepository, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('permanent_delete_application_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_applications');
        }

        $rh = $this->getCurrentRh();
        $application = $applicationRepository->findOneByRhIncludingDeleted($id, $rh);

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
    public function show(int $id, ApplicationRepository $applicationRepository, InterviewRepository $interviewRepository): Response
    {
        $rh = $this->getCurrentRh();
        $application = $applicationRepository->findOneByRhIncludingDeleted($id, $rh);

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
