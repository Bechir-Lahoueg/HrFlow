<?php

namespace App\Controller;

use App\Entity\Recrutement\Application;
use App\Entity\Recrutement\Candidate;
use App\Entity\Recrutement\JobOffer;
use App\Form\Recrutement\CandidateApplicationType;
use App\Form\Recrutement\CandidatePasswordChangeType;
use App\Form\Recrutement\CandidateProfileType;
use App\Repository\Recrutement\ApplicationRepository;
use App\Repository\Recrutement\CandidateRepository;
use App\Repository\Recrutement\InterviewRepository;
use App\Repository\Recrutement\JobOfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class CandidateController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ApplicationRepository $applicationRepository,
        private readonly CandidateRepository $candidateRepository,
        private readonly JobOfferRepository $jobOfferRepository,
        private readonly InterviewRepository $interviewRepository,
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
        $upcomingInterviews = $this->interviewRepository->findUpcomingByCandidate($candidate->getId());
        

        return $this->render('Candidate/dashboard.html.twig', [
            'stats' => $stats,
            'recentApplications' => array_slice($recentApplications, 0, 5),
            'upcomingInterviews' => array_slice($upcomingInterviews, 0, 3),
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
        $profileForm = $this->createForm(CandidateProfileType::class, $candidate);
        $passwordForm = $this->createForm(CandidatePasswordChangeType::class);

        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Votre profil a ete mis a jour avec succes.');
            return $this->redirectToRoute('app_candidate_profile');
        }

        // Get stats for the sidebar
        $stats = [
            'total' => $this->applicationRepository->countAllByCandidate($candidate),
            'interviews' => $this->applicationRepository->countByCandidateAndStatus($candidate, 'INTERVIEW'),
        ];

        return $this->render('Candidate/profile.html.twig', [
            'profileForm' => $profileForm->createView(),
            'passwordForm' => $passwordForm->createView(),
            'stats' => $stats,
        ]);
    }

    #[Route('/candidat/profil/mot-de-passe', name: 'app_candidate_change_password', methods: ['POST'])]
    #[IsGranted('ROLE_CANDIDATE')]
    public function changePassword(Request $request): Response
    {
        $candidate = $this->getCurrentCandidate();
        $form = $this->createForm(CandidatePasswordChangeType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();
            $newPassword = $form->get('newPassword')->getData();

            // Verify current password (SHA-256 hash)
            $hashedCurrentPassword = hash('sha256', $currentPassword);
            if ($candidate->getPassword() !== $hashedCurrentPassword) {
                $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
                return $this->redirectToRoute('app_candidate_profile');
            }

            // Hash and set new password
            $hashedNewPassword = hash('sha256', $newPassword);
            $candidate->setPassword($hashedNewPassword);
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a ete change avec succes.');
        } else {
            $this->addFlash('error', 'Veuillez verifier les informations saisies.');
        }

        return $this->redirectToRoute('app_candidate_profile');
    }

    #[Route('/candidat/profil/supprimer', name: 'app_candidate_delete_account', methods: ['POST'])]
    #[IsGranted('ROLE_CANDIDATE')]
    public function deleteAccount(Request $request): Response
    {
        $candidate = $this->getCurrentCandidate();

        // Verify confirmation field
        $confirmed = $request->request->get('delete_confirm');

        if ($confirmed !== 'DELETE') {
            $this->addFlash('error', 'Veuillez confirmer la suppression en tapant DELETE.');
            return $this->redirectToRoute('app_candidate_profile');
        }

        // Delete all related applications first (to avoid foreign key constraints)
        $applications = $this->applicationRepository->findByCandidate($candidate);
        foreach ($applications as $application) {
            $this->entityManager->remove($application);
        }

        // Delete the candidate
        $this->entityManager->remove($candidate);
        $this->entityManager->flush();

        // Clear session and logout
        $request->getSession()->invalidate();

        $this->addFlash('success', 'Votre compte a ete supprime avec succes.');
        return $this->redirectToRoute('app_home');
    }

    #[Route('/candidat/offres-emploi', name: 'app_candidate_job_offers')]
    #[IsGranted('ROLE_CANDIDATE')]
    public function jobOffers(Request $request): Response
    {
        $candidate = $this->getCurrentCandidate();

        $search = $request->query->get('search');
        $department = $request->query->get('department');
        $location = $request->query->get('location');
        $employmentType = $request->query->get('employmentType');

        $jobOffers = $this->jobOfferRepository->searchForCandidates($search, $department, $location, $employmentType);

        $appliedJobIds = [];
        foreach ($jobOffers as $offer) {
            $offerId = $offer->getId();
            if ($offerId !== null && $this->applicationRepository->hasCandidateApplied($candidate, $offerId)) {
                $appliedJobIds[] = $offerId;
            }
        }

        $departments = $this->jobOfferRepository->getDistinctDepartments();
        $locations = $this->jobOfferRepository->getDistinctLocations();
        $employmentTypes = $this->jobOfferRepository->getDistinctEmploymentTypes();

        return $this->render('Candidate/job_offers.html.twig', [
            'jobOffers' => $jobOffers,
            'appliedJobIds' => $appliedJobIds,
            'departments' => $departments,
            'locations' => $locations,
            'employmentTypes' => $employmentTypes,
            'filters' => [
                'search' => $search,
                'department' => $department,
                'location' => $location,
                'employmentType' => $employmentType,
            ],
        ]);
    }

    #[Route('/candidat/mes-entretiens', name: 'app_candidate_interviews')]
    #[IsGranted('ROLE_CANDIDATE')]
    public function interviews(): Response
    {
        $candidate = $this->getCurrentCandidate();
        $interviews = $this->interviewRepository->findByCandidate($candidate->getId());

        return $this->render('Candidate/interviews.html.twig', [
            'interviews' => $interviews,
        ]);
    }

    #[Route('/candidat/postuler/{id}', name: 'app_candidate_apply', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_CANDIDATE')]
    public function apply(Request $request, int $id): Response
    {
        $candidate = $this->getCurrentCandidate();
        $jobOffer = $this->jobOfferRepository->find($id);

        // Debug and validate job offer availability
        if (!$jobOffer) {
            throw $this->createNotFoundException('Cette offre d\'emploi n\'existe pas (ID: ' . $id . ').');
        }
        
        if ($jobOffer->isDeleted()) {
            $this->addFlash('error', 'Cette offre d\'emploi a ete supprimee et n\'est plus disponible.');
            return $this->redirectToRoute('app_candidate_job_offers');
        }
        
        if ($jobOffer->getStatus() !== 'OPEN') {
            $this->addFlash('error', 'Cette offre d\'emploi n\'est plus ouverte aux candidatures (Statut: ' . $jobOffer->getStatus() . ').');
            return $this->redirectToRoute('app_candidate_job_offers');
        }

        if ($this->applicationRepository->hasCandidateApplied($candidate, $id)) {
            $this->addFlash('error', 'Vous avez deja postule a cette offre.');
            return $this->redirectToRoute('app_candidate_job_offers');
        }

        $application = new Application();
        // Pre-populate required fields before form validation to avoid entity constraint errors
        $application->setJobOffer($jobOffer);
        $application->setCandidate($candidate);
        $application->setCandidateName($candidate->getFirstName() . ' ' . $candidate->getLastName());
        $application->setEmailAddress($candidate->getEmail());
        $application->setStatus('PENDING');
        $application->setAppliedAt(new \DateTime());
        $application->setSource('Candidate Portal');
        // Set temporary CV path - will be overwritten with actual file after upload
        $application->setCvPath('temp_' . uniqid());
        
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
        if ($user === null) {
            throw $this->createAccessDeniedException('Non authentifié.');
        }
        $candidate = $this->candidateRepository->findOneBy(['username' => $user->getUserIdentifier()]);

        if (!$candidate) {
            throw $this->createAccessDeniedException('Candidat non trouve.');
        }

        return $candidate;
    }

    private function uploadFile(UploadedFile $file, string $prefix): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        
        // Sanitize filename - works with or without intl extension
        $safeFilename = $this->sanitizeFilename($originalFilename);
        
        // Use guessExtension() if available, fallback to client original extension
        try {
            $extension = $file->guessExtension() ?: $file->getClientOriginalExtension();
        } catch (\Exception $e) {
            // If MIME guessing fails (fileinfo not available), use client extension
            $extension = $file->getClientOriginalExtension() ?: 'bin';
        }
        
        // Validate extension for security
        $allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'rtf'];
        $extension = strtolower($extension);
        if (!in_array($extension, $allowedExtensions)) {
            $extension = 'pdf'; // Default to pdf if extension not allowed
        }
        
        $newFilename = $prefix . '_' . $safeFilename . '_' . uniqid() . '.' . $extension;

        $projectDir = is_scalar($this->getParameter('kernel.project_dir')) ? (string) $this->getParameter('kernel.project_dir') : '';
        $uploadPath = $projectDir . '/public' . $this->uploadDir;
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }

        $file->move($uploadPath, $newFilename);

        return $this->uploadDir . $newFilename;
    }

    /**
     * Sanitize filename to be safe for filesystem - works without intl extension
     */
    private function sanitizeFilename(string $filename): string
    {
        // Transliterate if intl is available, otherwise use iconv or basic replacement
        if (function_exists('transliterator_transliterate')) {
            $filename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $filename);
        } elseif (function_exists('iconv')) {
            // Fallback to iconv if available
            $filename = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);
            $filename = strtolower((string) $filename);
            $filename = (string) preg_replace('/[^a-z0-9_]/', '_', $filename);
        } else {
            // Basic fallback - just remove/replace unsafe characters
            $filename = strtolower($filename);
            // Replace common accented chars
            $replacements = [
                'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
                'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
                'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
                'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
                'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
                'ñ' => 'n', 'ç' => 'c', 'ß' => 'ss',
                'ÿ' => 'y', 'ý' => 'y',
            ];
            $filename = strtr($filename, $replacements);
            // Replace any remaining non-alphanumeric chars with underscore
            $filename = (string) preg_replace('/[^a-z0-9_]/', '_', $filename);
        }
        
        // Ensure we don't have multiple underscores
        $filename = (string) preg_replace('/_+/', '_', (string) $filename);
        // Trim underscores from ends
        $filename = trim($filename, '_');
        
        return $filename ?: 'file';
    }
}
