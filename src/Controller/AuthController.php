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
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class AuthController extends AbstractController
{
    public function __construct(
        private readonly EmployeeRepository $employeeRepository,
        private readonly LeaveRequestRepository $leaveRequestRepository,
        private readonly JobOfferRepository $jobOfferRepository,
        private readonly CandidateRepository $candidateRepository,
        private readonly DbUserProvider $dbUserProvider,
        private readonly GoogleAuthenticatorService $googleAuthenticatorService,
        private readonly CacheInterface $cache,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Cache login page stats for 10 minutes — purely decorative numbers
        $loginStats = $this->cache->get('login_page_stats', function (ItemInterface $item): array {
            $item->expiresAfter(600);
            return [
                'employeesCount' => $this->employeeRepository->count([]),
                'pendingLeavesCount' => $this->leaveRequestRepository->count(['status' => 'ATTENTE']),
                'openPositionsCount' => $this->jobOfferRepository->countPublicOpenOffers(),
                'candidatesCount' => $this->candidateRepository->count([]),
            ];
        });

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
            return new JsonResponse(['exists' => false, 'required' => false]);
        }

        // Pas de cache : la détection alimente un flux de login en 2 étapes, on veut
        // l'état réel (un compte qui vient d'activer/désactiver la 2FA ne doit pas rester
        // bloqué 60s sur un état périmé).
        $exists = false;
        $required = false;
        $displayName = null;

        try {
            $user = $this->dbUserProvider->loadUserByIdentifier($identifier);
            if ($user instanceof DbUser) {
                $exists = true;
                $required = $this->googleAuthenticatorService->isEnabled($user);
                $displayName = $user->getFullName() ?: $user->getUsername();
            }
        } catch (\Throwable) {
            // Identifiant inconnu — on ne révèle pas l'existence dans l'UI étape 1,
            // mais on laisse l'étape 2 s'afficher pour ne pas faire d'énumération de comptes.
        }

        return new JsonResponse([
            'exists' => $exists,
            'required' => $required,
            'displayName' => $displayName,
        ]);
    }

    /**
     * Fallback "code par email" : si l'utilisateur n'a plus accès à son app
     * authenticator, il peut recevoir un code temporaire par email.
     * Le code est stocké hashé en session avec une expiration courte.
     */
    #[Route('/auth/2fa-email-code', name: 'app_auth_2fa_email_code', methods: ['POST'])]
    public function sendEmailFallbackCode(Request $request, \App\Service\Shared\HrFlowMailer $mailer): JsonResponse
    {
        $identifier = trim((string) $request->request->get('identifier', ''));
        if (mb_strlen($identifier) < 3) {
            return new JsonResponse(['sent' => false], 400);
        }

        // Réponse générique quoi qu'il arrive (anti-énumération de comptes).
        // On retourne aussi un CSRF token frais : après ce POST, Symfony peut régénérer
        // l'ID de session, ce qui invaliderait le token pré-rendu dans le HTML.
        $freshCsrf = $this->csrfTokenManager->getToken('authenticate')->getValue();
        $generic = new JsonResponse(['sent' => true, 'csrf' => $freshCsrf]);

        try {
            $user = $this->dbUserProvider->loadUserByIdentifier($identifier);
            if (!$user instanceof DbUser || !$this->googleAuthenticatorService->isEnabled($user)) {
                return $generic;
            }
            $email = $user->getEmail();
            if (!$email) {
                return $generic;
            }

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $session = $request->getSession();
            $session->set('2fa_email_fallback', [
                'identifier' => $identifier,
                'hash' => password_hash($code, PASSWORD_DEFAULT),
                'expires' => time() + 600, // 10 minutes
                'attempts' => 0,
            ]);

            $mailer->sendTwoFactorFallbackCode(
                $email,
                $user->getFullName() ?: $user->getUsername(),
                $code
            );
        } catch (\Throwable) {
            // On reste générique.
        }

        return $generic;
    }
}
