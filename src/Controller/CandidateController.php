<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CandidateController extends AbstractController
{
    #[Route('/candidat/espace', name: 'app_candidate_dashboard')]
    #[IsGranted('ROLE_CANDIDATE')]
    public function dashboard(): Response
    {
        return $this->render('Candidate/dashboard.html.twig');
    }

    #[Route('/candidat/mes-candidatures', name: 'app_candidate_applications')]
    #[IsGranted('ROLE_CANDIDATE')]
    public function applications(): Response
    {
        return $this->render('Candidate/applications.html.twig');
    }

    #[Route('/candidat/profil', name: 'app_candidate_profile')]
    #[IsGranted('ROLE_CANDIDATE')]
    public function profile(): Response
    {
        return $this->render('Candidate/profile.html.twig');
    }
}
