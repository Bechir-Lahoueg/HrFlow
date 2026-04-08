<?php

namespace App\Controller;

use App\Entity\Applicaiton;
use App\Entity\Candidate;
use App\Entity\JobOffer;
use App\Form\CandidateApplicationType;
use App\Form\CandidateProfileType;
use App\Repository\ApplicaitonRepository;
use App\Repository\CandidateRepository;
use App\Repository\JobOfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CandidateController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ApplicaitonRepository $applicationRepository,
        private readonly CandidateRepository $candidateRepository,
        private readonly JobOfferRepository $jobOfferRepository,
        private readonly string $uploadDir = '/uploads/cv/'
    ) {
    }

    #[Route('/candidat/espace', name: 'app_candidate_dashboard')]
    #[IsGranted('ROLE_CANDIDATE')]
    public function dashboard(): Response
    {
        $candidate = $this->getCurrentCandidate();

        $stats = [
            'total' => $this->applicationRepository->countAllByCandidate($candidate),
            'pending' => $this->applicationRepository->countByCandidateAndStatus($candidate, 'PENDING'),
            'reviewing' => $this->applicationRepository->countByCandidateAndStatus($candidate, 'REVIEWING'),
            'interview' => $this->applicationRepository->countByCandidateAndStatus($candidate, 'INTERVIEW'),
            'offer' => $this->applicationRepository->countByCandidateAndStatus($candidate, 'OFFER'),
            'hired' => $this->applicationRepository->countByCandidateAndStatus($candidate, 'HIRED'),
            'rejected' => $this->applicationRepository->countByCandidateAndStatus($candidate, 'REJECTED'),
        ];

        $recentApplications = $this->applicationRepository->findByCandidate($candidate);

        return $this->render('Candidate/dashboard.html.twig', [
            'stats' => $stats,
            'recentApplications' => array_slice($recentApplications, 0, 5),
        ]);
    }

    #[Route('/candidat/mes-candidatures', name: 'app_candidate_applications')]
    #[IsGranted('ROLE_CANDIDATE')]
    public function applications(): Response
    {
        $candidate = $this->getCurrentCandidate();
        $applications = $this->applicationRepository->findByCandidate($candidate);

        return $this->render('Candidate/applications.html.twig', [
            'applications' => $applications,
        ]);
    }

    #[Route('/candidat/profil', name: 'app_candidate_profile')]
    #[IsGranted('ROLE_CANDIDATE')]
    public function profile(Request $request): Response
    {
        $candidate = $this->getCurrentCandidate();
        $form = $this->createForm(CandidateProfileType::class, $candidate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Votre profil a ete mis a jour avec succes.');
            return $this->redirectToRoute('app_candidate_profile');
        }

        return $this->render('Candidate/profile.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/candidat/offres-emploi', name: 'app_candidate_job_offers')]
    #[IsGranted('ROLE_CANDIDATE')]
    public function jobOffers(): Response
    {
        $candidate = $this->getCurrentCandidate();
        $jobOffers = $this->jobOfferRepository->findPublished(100);

        $appliedJobIds = [];
        foreach ($jobOffers as $offer) {
            if ($this->applicationRepository->hasCandidateApplied($candidate, $offer->getId())) {
                $appliedJobIds[] = $offer->getId();
            }
        }

        return $this->render('Candidate/job_offers.html.twig', [
            'jobOffers' => $jobOffers,
            'appliedJobIds' => $appliedJobIds,
        ]);
    }

    #[Route('/candidat/postuler/{id}', name: 'app_candidate_apply', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_CANDIDATE')]
    public function apply(Request $request, int $id): Response
    {
        $candidate = $this->getCurrentCandidate();
        $jobOffer = $this->jobOfferRepository->find($id);

        if (!$jobOffer || $jobOffer->isDeleted() || $jobOffer->getStatus() !== 'OPEN') {
            throw $this->createNotFoundException('Cette offre d\'emploi n\'existe pas ou n\'est plus disponible.');
        }

        if ($this->applicationRepository->hasCandidateApplied($candidate, $id)) {
            $this->addFlash('error', 'Vous avez deja postule a cette offre.');
            return $this->redirectToRoute('app_candidate_job_offers');
        }

        $application = new Applicaiton();
        $form = $this->createForm(CandidateApplicationType::class, $application);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $cvFile = $form->get('cvFile')->getData();
            $coverLetterFile = $form->get('coverLetterFile')->getData();

            if ($cvFile instanceof UploadedFile) {
                $cvFilename = $this->uploadFile($cvFile, 'cv');
                $application->setCvPath($cvFilename);
            }

            if ($coverLetterFile instanceof UploadedFile) {
                $coverLetterFilename = $this->uploadFile($coverLetterFile, 'cover_letter');
                $application->setCoverLetterPath($coverLetterFilename);
            }

            $application->setCandidate($candidate);
            $application->setJobOffer($jobOffer);
            $application->setCandidateName($candidate->getFirstName() . ' ' . $candidate->getLastName());
            $application->setEmailAddress($candidate->getEmail());
            $application->setStatus('PENDING');
            $application->setAppliedAt(new \DateTime());
            $application->setSource('Candidate Portal');

            $this->entityManager->persist($application);
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre candidature a ete envoyee avec succes !');
            return $this->redirectToRoute('app_candidate_applications');
        }

        return $this->render('Candidate/apply.html.twig', [
            'form' => $form->createView(),
            'jobOffer' => $jobOffer,
        ]);
    }

    private function getCurrentCandidate(): Candidate
    {
        $user = $this->getUser();
        $candidate = $this->candidateRepository->findOneBy(['username' => $user->getUserIdentifier()]);

        if (!$candidate) {
            throw $this->createAccessDeniedException('Candidat non trouve.');
        }

        return $candidate;
    }

    private function uploadFile(UploadedFile $file, string $prefix): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
        $newFilename = $prefix . '_' . $safeFilename . '_' . uniqid() . '.' . $file->guessExtension();

        $uploadPath = $this->getParameter('kernel.project_dir') . '/public' . $this->uploadDir;
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }

        $file->move($uploadPath, $newFilename);

        return $this->uploadDir . $newFilename;
    }
}
