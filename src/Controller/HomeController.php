<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\Recrutement\JobOfferRepository;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('Home/LandingPage.html.twig');
    }

    #[Route('/offres-emploi', name: 'app_job_offers')]
    public function jobOffers(JobOfferRepository $jobOfferRepository): Response
    {
        $jobOffers = $jobOfferRepository->findPublished();

        return $this->render('Home/job_offers.html.twig', [
            'jobOffers' => $jobOffers,
        ]);
    }
}
