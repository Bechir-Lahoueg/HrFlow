<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use App\Service\Shared\HrFlowMailer;
use App\Service\Security\GoogleAuthenticatorService;

final class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';
    public const CANDIDATE_LOGIN_ROUTE = 'app_candidate_login';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly DbUserProvider $userProvider,
        private readonly HrFlowMailer $hrFlowMailer,
        private readonly GoogleAuthenticatorService $googleAuthenticatorService,
    ) {
    }

    public function supports(Request $request): bool
    {
        if (!$request->isMethod('POST')) {
            return false;
        }

        $path = $request->getPathInfo();
        $loginPath = $this->urlGenerator->generate(self::LOGIN_ROUTE);
        $candidateLoginPath = $this->urlGenerator->generate(self::CANDIDATE_LOGIN_ROUTE);

        return $path === $loginPath || $path === $candidateLoginPath;
    }

    public function authenticate(Request $request): Passport
    {
        $identifier = trim((string) $request->request->get('identifier', ''));
        $password = (string) $request->request->get('password', '');
        $totpCode = (string) $request->request->get('totp_code', '');
        $csrfToken = (string) $request->request->get('_csrf_token', '');

        $request->getSession()->set('_security.last_username', $identifier);

        return new Passport(
            new UserBadge($identifier, fn (string $userIdentifier) => $this->userProvider->loadUserByIdentifier($userIdentifier)),
            new CustomCredentials(
                function (array $credentials, PasswordAuthenticatedUserInterface $user): bool {
                    $isPasswordValid = $this->isPasswordValid($credentials['password'], $user->getPassword());
                    if (!$isPasswordValid) {
                        return false;
                    }

                    if (!$user instanceof DbUser) {
                        return true;
                    }

                    if (!$this->googleAuthenticatorService->isEnabled($user)) {
                        return true;
                    }

                    $totpCode = preg_replace('/\D+/', '', (string) $credentials['totp_code']) ?? '';
                    if ($totpCode === '') {
                        throw new CustomUserMessageAuthenticationException('Code Google Authenticator requis pour ce compte.');
                    }

                    if (!$this->googleAuthenticatorService->verifyForUser($user, $totpCode)) {
                        throw new CustomUserMessageAuthenticationException('Code Google Authenticator invalide.');
                    }

                    return true;
                },
                [
                    'password' => $password,
                    'totp_code' => $totpCode,
                ]
            ),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $roles = $token->getRoleNames();
        $this->removeTargetPath($request->getSession(), $firewallName);

        // Send login alert email for RH and Admin only
        $user = $token->getUser();
        if ($user instanceof DbUser && (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_RH', $roles, true))) {
            $email = $user->getEmail();
            if ($email) {
                $this->hrFlowMailer->sendLoginAlert(
                    $email,
                    $user->getFullName() ?? $user->getUsername(),
                    in_array('ROLE_ADMIN', $roles, true) ? 'Administrateur' : 'RH',
                    $request->getClientIp() ?? 'Inconnu',
                    $request->headers->get('User-Agent', 'Inconnu')
                );
            }
        }

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_welcome_admin'));
        }

        if (in_array('ROLE_RH', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_welcome_rh'));
        }

        if (in_array('ROLE_EMPLOYEE', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_welcome_employee'));
        }

        if (in_array('ROLE_CANDIDATE', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_candidate_dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_welcome'));
    }

    protected function getLoginUrl(Request $request): string
    {
        // Check if request came from candidate login page
        if ($request->getPathInfo() === $this->urlGenerator->generate(self::CANDIDATE_LOGIN_ROUTE)) {
            return $this->urlGenerator->generate(self::CANDIDATE_LOGIN_ROUTE);
        }
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }

    private function isPasswordValid(string $plainPassword, string $storedPassword): bool
    {
        // Keep compatibility with historical SHA-256 and plain-text records.
        if (password_get_info($storedPassword)['algo'] !== null) {
            return password_verify($plainPassword, $storedPassword);
        }

        return hash_equals($storedPassword, hash('sha256', $plainPassword))
            || hash_equals($storedPassword, $plainPassword);
    }
}
