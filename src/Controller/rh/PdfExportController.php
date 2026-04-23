<?php

namespace App\Controller\rh;

use App\Repository\Recrutement\ApplicationRepository;
use App\Repository\Recrutement\InterviewRepository;
use App\Repository\Recrutement\JobOfferRepository;
use App\Repository\Rh\UserRepository;
use App\Security\DbUser;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

#[Route('/rh/recruitment')]
#[IsGranted('ROLE_RH')]
class PdfExportController extends AbstractController
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    #[Route('/job-offers/{id}/pdf', name: 'app_rh_job_offer_pdf', methods: ['GET'])]
    public function exportJobOffer(
        int $id,
        JobOfferRepository $jobOfferRepository
    ): Response {
        $rh = $this->getCurrentRh();
        $jobOffer = $jobOfferRepository->findOneByRh($id, $rh);

        if (!$jobOffer) {
            $this->addFlash('error', 'Offre d\'emploi non trouvée.');
            return $this->redirectToRoute('app_rh_job_offers');
        }

        $html = $this->twig->render('pdf/job_offer.html.twig', [
            'jobOffer' => $jobOffer,
            'generatedAt' => new \DateTime(),
        ]);

        return $this->generatePdfResponse(
            $html,
            sprintf('offre-emploi-%s.pdf', $this->sanitizeFilename($jobOffer->getTitle()))
        );
    }

    #[Route('/applications/{id}/pdf', name: 'app_rh_application_pdf', methods: ['GET'])]
    public function exportApplication(
        int $id,
        ApplicationRepository $applicationRepository
    ): Response {
        $rh = $this->getCurrentRh();
        $application = $applicationRepository->findOneByRh($id, $rh);

        if (!$application) {
            $this->addFlash('error', 'Candidature non trouvée.');
            return $this->redirectToRoute('app_rh_applications');
        }

        $html = $this->twig->render('pdf/application.html.twig', [
            'application' => $application,
            'jobOffer' => $application->getJobOffer(),
            'candidate' => $application->getCandidate(),
            'generatedAt' => new \DateTime(),
        ]);

        return $this->generatePdfResponse(
            $html,
            sprintf('candidature-%s.pdf', $this->sanitizeFilename($application->getCandidateName()))
        );
    }

    #[Route('/interviews/{id}/pdf', name: 'app_rh_interview_pdf', methods: ['GET'])]
    public function exportInterview(
        int $id,
        InterviewRepository $interviewRepository,
        UserRepository $userRepository
    ): Response {
        $rh = $this->getCurrentRh();
        $interview = $interviewRepository->findOneByRhIncludingDeleted($id, $rh);

        if (!$interview) {
            $this->addFlash('error', 'Entretien non trouvé.');
            return $this->redirectToRoute('app_rh_interviews');
        }

        $interviewer = $interview->getInterviewerId()
            ? $userRepository->find($interview->getInterviewerId())
            : null;

        $html = $this->twig->render('pdf/interview.html.twig', [
            'interview' => $interview,
            'application' => $interview->getApplication(),
            'jobOffer' => $interview->getApplication()?->getJobOffer(),
            'interviewer' => $interviewer,
            'generatedAt' => new \DateTime(),
        ]);

        $candidateName = $interview->getApplication()?->getCandidateName() ?? 'candidat';
        return $this->generatePdfResponse(
            $html,
            sprintf('entretien-%s.pdf', $this->sanitizeFilename($candidateName))
        );
    }

    #[Route('/applications/pdf/bulk', name: 'app_rh_applications_pdf_bulk', methods: ['POST'])]
    public function exportApplicationsBulk(
        ApplicationRepository $applicationRepository
    ): Response {
        $rh = $this->getCurrentRh();
        
        // Get selected application IDs from request
        $applicationIds = $_POST['application_ids'] ?? [];
        
        if (empty($applicationIds)) {
            $this->addFlash('error', 'Aucune candidature sélectionnée.');
            return $this->redirectToRoute('app_rh_applications');
        }

        $applications = [];
        foreach ($applicationIds as $id) {
            $application = $applicationRepository->findOneByRh((int) $id, $rh);
            if ($application) {
                $applications[] = $application;
            }
        }

        if (empty($applications)) {
            $this->addFlash('error', 'Aucune candidature trouvée.');
            return $this->redirectToRoute('app_rh_applications');
        }

        $html = $this->twig->render('pdf/applications_bulk.html.twig', [
            'applications' => $applications,
            'generatedAt' => new \DateTime(),
            'count' => count($applications),
        ]);

        return $this->generatePdfResponse(
            $html,
            sprintf('candidatures-export-%s.pdf', date('Y-m-d'))
        );
    }

    #[Route('/job-offers/pdf/bulk', name: 'app_rh_job_offers_pdf_bulk', methods: ['POST'])]
    public function exportJobOffersBulk(
        JobOfferRepository $jobOfferRepository
    ): Response {
        $rh = $this->getCurrentRh();
        
        $jobOfferIds = $_POST['job_offer_ids'] ?? [];
        
        if (empty($jobOfferIds)) {
            $this->addFlash('error', 'Aucune offre sélectionnée.');
            return $this->redirectToRoute('app_rh_job_offers');
        }

        $jobOffers = [];
        foreach ($jobOfferIds as $id) {
            $jobOffer = $jobOfferRepository->findOneByRh((int) $id, $rh);
            if ($jobOffer) {
                $jobOffers[] = $jobOffer;
            }
        }

        if (empty($jobOffers)) {
            $this->addFlash('error', 'Aucune offre trouvée.');
            return $this->redirectToRoute('app_rh_job_offers');
        }

        $html = $this->twig->render('pdf/job_offers_bulk.html.twig', [
            'jobOffers' => $jobOffers,
            'generatedAt' => new \DateTime(),
            'count' => count($jobOffers),
        ]);

        return $this->generatePdfResponse(
            $html,
            sprintf('offres-emploi-export-%s.pdf', date('Y-m-d'))
        );
    }

    /**
     * Generate PDF from HTML and return as response
     */
    private function generatePdfResponse(string $html, string $filename): StreamedResponse
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new StreamedResponse(function () use ($dompdf) {
            echo $dompdf->output();
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    /**
     * Sanitize filename for PDF export
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove accents
        $filename = iconv('UTF-8', 'ASCII//TRANSLIT', $filename);
        // Replace non-alphanumeric characters with hyphens
        $filename = preg_replace('/[^A-Za-z0-9\-]/', '-', $filename);
        // Remove multiple consecutive hyphens
        $filename = preg_replace('/-+/', '-', $filename);
        // Trim hyphens from beginning and end
        $filename = trim($filename, '-');
        // Convert to lowercase
        $filename = strtolower($filename);
        // Limit length
        $filename = substr($filename, 0, 50);
        
        return $filename;
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