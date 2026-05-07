<?php

namespace App\Controller;

use App\Entity\Recrutement\Candidate;
use App\Form\Recrutement\CandidateLoginFormType;
use App\Form\Recrutement\CandidateRegistrationFormType;
use App\Repository\Recrutement\ApplicationRepository;
use App\Repository\Recrutement\CandidateRepository;
use App\Repository\Recrutement\JobOfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class CandidateAuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CandidateRepository $candidateRepository,
        private readonly JobOfferRepository $jobOfferRepository,
        private readonly ApplicationRepository $applicationRepository,
    ) {
    }

    #[Route('/candidat/inscription', name: 'app_candidate_register')]
    public function register(Request $request): Response
    {
        $candidate = new Candidate();
        $form = $this->createForm(CandidateRegistrationFormType::class, $candidate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if username or email already exists
            if ($this->candidateRepository->existsByUsernameOrEmail(
                $candidate->getUsername() ?? '',
                $candidate->getEmail() ?? ''
            )) {
                $this->addFlash('error', 'Ce nom d\'utilisateur ou email est deja utilise.');
                return $this->render('Auth/candidate_register.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            // Hash password using SHA-256 (to match existing system)
            $plainPassword = $form->get('plainPassword')->getData();
            $hashedPassword = hash('sha256', $plainPassword);
            $candidate->setPassword($hashedPassword);

            // Save candidate
            $this->entityManager->persist($candidate);
            $this->entityManager->flush();

            // Redirect to login page with success message
            $this->addFlash('success', 'Votre compte a ete cree avec succes ! Vous pouvez maintenant vous connecter.');
            return $this->redirectToRoute('app_candidate_login');
        }

        return $this->render('Auth/candidate_register.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/candidat/connexion', name: 'app_candidate_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $form = $this->createForm(CandidateLoginFormType::class, null, [
            'action' => $this->generateUrl('app_login'),
        ]);

        $form->get('identifier')->setData($authenticationUtils->getLastUsername());

        $candidateStats = [
            'openOffersCount' => $this->jobOfferRepository->countPublicOpenOffers(),
            'candidatesCount' => $this->candidateRepository->count([]),
            'responseRate' => $this->applicationRepository->getGlobalResponseRate(),
        ];

        return $this->render('Auth/candidate_login.html.twig', [
            'form' => $form->createView(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'candidateStats' => $candidateStats,
        ]);
    }
}
