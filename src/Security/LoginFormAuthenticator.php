<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly DbUserProvider $userProvider,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $identifier = trim((string) $request->request->get('identifier', ''));
        $password = (string) $request->request->get('password', '');
        $csrfToken = (string) $request->request->get('_csrf_token', '');

        $request->getSession()->set('_security.last_username', $identifier);

        return new Passport(
            new UserBadge($identifier, fn (string $userIdentifier) => $this->userProvider->loadUserByIdentifier($userIdentifier)),
            new CustomCredentials(
                fn (string $plainPassword, PasswordAuthenticatedUserInterface $user): bool => $this->isPasswordValid($plainPassword, $user->getPassword()),
                $password
            ),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $roles = $token->getRoleNames();

        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);
        if ($targetPath !== null) {
            return new RedirectResponse($targetPath);
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
