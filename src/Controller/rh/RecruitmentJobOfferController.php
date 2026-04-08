<?php

namespace App\Controller\rh;

use App\Entity\JobOffer;
use App\Form\JobOfferType;
use App\Repository\ApplicationRepository;
use App\Repository\JobOfferRepository;
use App\Security\DbUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class RecruitmentJobOfferController extends AbstractController
{
    #[Route('/rh/recruitment/job-offers', name: 'app_rh_job_offers', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function index(Request $request, JobOfferRepository $jobOfferRepository): Response
    {
        $status = $request->query->get('status');
        $rh = $this->getCurrentRh();

        $jobOffers = $jobOfferRepository->findByRhWithApplicationCounts($rh, $status);
        $stats = [
            'totalOffers' => $jobOfferRepository->countByRh($rh),
            'totalApplications' => $jobOfferRepository->countTotalApplications($rh),
            'statusCounts' => $jobOfferRepository->getStatusStats($rh),
        ];

        // Create form for new job offer
        $jobOffer = new JobOffer();
        $jobOffer->setCreatedBy($rh->getId());
        $jobOffer->setCreatedAt(new \DateTime());
        $form = $this->createForm(JobOfferType::class, $jobOffer, ['is_edit' => false]);

        return $this->render('Recrutement/recruitment_job_offers.html.twig', [
            'jobOffers' => $jobOffers,
            'stats' => $stats,
            'form' => $form->createView(),
            'deletedJobOffers' => $jobOfferRepository->findDeletedByRh($rh),
        ]);
    }

    #[Route('/rh/recruitment/job-offers/create', name: 'app_rh_job_offers_create', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function create(Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $jobOffer = new JobOffer();
        $jobOffer->setCreatedBy($this->getCurrentRh()->getId());
        $jobOffer->setCreatedAt(new \DateTime());

        $form = $this->createForm(JobOfferType::class, $jobOffer, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($jobOffer);
            $em->flush();

            $this->addFlash('success', 'Offre d\'emploi créée avec succès.');
            return $this->redirectToRoute('app_rh_job_offers');
        }

        // If form has errors, flash them
        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_rh_job_offers');
    }

    #[Route('/rh/recruitment/job-offers/{id}/edit', name: 'app_rh_job_offers_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RH')]
    public function edit(int $id, Request $request, JobOfferRepository $jobOfferRepository, EntityManagerInterface $em): Response
    {
        $rh = $this->getCurrentRh();
        $jobOffer = $jobOfferRepository->findOneByRh($id, $rh);

        if (!$jobOffer) {
            $this->addFlash('error', 'Offre d\'emploi non trouvée.');
            return $this->redirectToRoute('app_rh_job_offers');
        }

        $form = $this->createForm(JobOfferType::class, $jobOffer, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Offre d\'emploi mise à jour avec succès.');
            return $this->redirectToRoute('app_rh_job_offers');
        }

        return $this->render('Recrutement/job_offer_edit.html.twig', [
            'jobOffer' => $jobOffer,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/rh/recruitment/job-offers/{id}/delete', name: 'app_rh_job_offers_delete', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function delete(int $id, Request $request, JobOfferRepository $jobOfferRepository, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('delete_job_offer_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_job_offers');
        }

        $rh = $this->getCurrentRh();
        $jobOffer = $jobOfferRepository->findOneByRh($id, $rh);

        if (!$jobOffer) {
            $this->addFlash('error', 'Offre d\'emploi non trouvée.');
            return $this->redirectToRoute('app_rh_job_offers');
        }

        // Soft delete
        $jobOffer->setIsDeleted(true);
        $em->flush();

        $this->addFlash('success', 'Offre d\'emploi supprimée avec succès.');
        return $this->redirectToRoute('app_rh_job_offers');
    }

    #[Route('/rh/recruitment/job-offers/{id}/restore', name: 'app_rh_job_offers_restore', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function restore(int $id, Request $request, JobOfferRepository $jobOfferRepository, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('restore_job_offer_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_job_offers');
        }

        $rh = $this->getCurrentRh();
        // Find including deleted
        $jobOffer = $jobOfferRepository->findOneByRhIncludingDeleted($id, $rh);

        if (!$jobOffer) {
            $this->addFlash('error', 'Offre d\'emploi non trouvée.');
            return $this->redirectToRoute('app_rh_job_offers');
        }

        $jobOffer->setIsDeleted(false);
        $em->flush();

        $this->addFlash('success', 'Offre d\'emploi restaurée avec succès.');
        return $this->redirectToRoute('app_rh_job_offers');
    }

    #[Route('/rh/recruitment/job-offers/{id}/delete-permanent', name: 'app_rh_job_offers_delete_permanent', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function deletePermanent(int $id, Request $request, JobOfferRepository $jobOfferRepository, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('permanent_delete_job_offer_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_job_offers');
        }

        $rh = $this->getCurrentRh();
        // Find including deleted
        $jobOffer = $jobOfferRepository->findOneByRhIncludingDeleted($id, $rh);

        if (!$jobOffer) {
            $this->addFlash('error', 'Offre d\'emploi non trouvée.');
            return $this->redirectToRoute('app_rh_job_offers');
        }

        $em->remove($jobOffer);
        $em->flush();

        $this->addFlash('success', 'Offre d\'emploi définitivement supprimée.');
        return $this->redirectToRoute('app_rh_job_offers');
    }

    #[Route('/rh/recruitment/job-offers/{id}', name: 'app_rh_job_offers_show', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function show(int $id, JobOfferRepository $jobOfferRepository, ApplicationRepository $applicaitonRepository): Response
    {
        $rh = $this->getCurrentRh();
        $jobOffer = $jobOfferRepository->findOneByRhIncludingDeleted($id, $rh);

        if (!$jobOffer) {
            $this->addFlash('error', 'Offre d\'emploi non trouvée.');
            return $this->redirectToRoute('app_rh_job_offers');
        }

        // Get applications for this job offer
        $applications = $applicaitonRepository->findByJobOffer($jobOffer->getId(), $rh);

        return $this->render('Recrutement/job_offer_details.html.twig', [
            'jobOffer' => $jobOffer,
            'applications' => $applications,
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
