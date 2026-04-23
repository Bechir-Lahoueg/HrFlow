<?php

namespace App\Controller;

use App\Repository\Recrutement\CandidateRepository;
use App\Repository\Recrutement\JobOfferRepository;
use App\Repository\Rh\EmployeeRepository;
use App\Repository\Rh\LeaveRequestRepository;
use App\Security\DbUser;
use App\Security\DbUserProvider;
use App\Service\Security\GoogleAuthenticatorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class AuthController extends AbstractController
{
    public function __construct(
        private readonly EmployeeRepository $employeeRepository,
        private readonly LeaveRequestRepository $leaveRequestRepository,
        private readonly JobOfferRepository $jobOfferRepository,
        private readonly CandidateRepository $candidateRepository,
        private readonly DbUserProvider $dbUserProvider,
        private readonly GoogleAuthenticatorService $googleAuthenticatorService,
    ) {
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $loginStats = [
            'employeesCount' => $this->employeeRepository->count([]),
            'pendingLeavesCount' => $this->leaveRequestRepository->count(['status' => 'ATTENTE']),
            'openPositionsCount' => $this->jobOfferRepository->countPublicOpenOffers(),
            'candidatesCount' => $this->candidateRepository->count([]),
        ];

        return $this->render('Auth/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'loginStats' => $loginStats,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method is intercepted by the firewall logout key.');
    }

    #[Route('/auth/2fa-status', name: 'app_auth_2fa_status', methods: ['GET'])]
    public function twoFactorStatus(Request $request): JsonResponse
    {
        $identifier = trim((string) $request->query->get('identifier', ''));
        if (mb_strlen($identifier) < 3) {
            return new JsonResponse(['required' => false]);
        }

        try {
            $user = $this->dbUserProvider->loadUserByIdentifier($identifier);
            if ($user instanceof DbUser) {
                return new JsonResponse(['required' => $this->googleAuthenticatorService->isEnabled($user)]);
            }
        } catch (\Throwable) {
            // Keep neutral response for unknown identifier.
        }

        return new JsonResponse(['required' => false]);
    }
}
