<?php

namespace App\Controller;

use App\Repository\Recrutement\JobOfferRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private JobOfferRepository $jobOfferRepository
    ) {
    }
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('Home/LandingPage.html.twig');
    }

    #[Route('/offres-emploi', name: 'app_job_offers')]
    public function jobOffers(): Response
    {
        $jobOffers = $this->jobOfferRepository->findPublished(50);

        return $this->render('Home/job_offers.html.twig', [
            'jobOffers' => $jobOffers,
        ]);
    }

    #[Route('/a-propos', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('Home/about.html.twig');
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(): Response
    {
        return $this->render('Home/contact.html.twig');
    }

    #[Route('/tarification', name: 'app_subscriptions')]
    public function subscriptions(): Response
    {
        return $this->render('Home/subscriptions.html.twig');
    }
}
