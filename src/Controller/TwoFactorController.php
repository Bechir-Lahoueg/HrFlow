<?php

namespace App\Controller;

use App\Security\DbUser;
use App\Service\Security\GoogleAuthenticatorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TwoFactorController extends AbstractController
{
    #[Route('/security/2fa/setup', name: 'app_2fa_setup', methods: ['GET', 'POST'])]
    public function setup(Request $request, GoogleAuthenticatorService $googleAuthenticatorService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof DbUser) {
            throw $this->createAccessDeniedException('Utilisateur non supporte pour la double authentification.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('2fa_setup', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_2fa_setup');
            }

            $action = (string) $request->request->get('action', 'enable');
            if ($action === 'disable') {
                $googleAuthenticatorService->disableForUser($user);
                $request->getSession()->remove('two_factor_pending_secret');
                $this->addFlash('success', 'Google Authenticator a ete desactive pour votre compte.');
                return $this->redirectToRoute('app_2fa_setup');
            }

            $pendingSecret = (string) $request->getSession()->get('two_factor_pending_secret', '');
            if ($pendingSecret === '') {
                $pendingSecret = $googleAuthenticatorService->generateSecret();
                $request->getSession()->set('two_factor_pending_secret', $pendingSecret);
            }

            $code = (string) $request->request->get('code', '');
            if (!$googleAuthenticatorService->verifyCode($pendingSecret, $code)) {
                $this->addFlash('error', 'Code Google Authenticator invalide.');
                return $this->redirectToRoute('app_2fa_setup');
            }

            $googleAuthenticatorService->enableForUser($user, $pendingSecret);
            $request->getSession()->remove('two_factor_pending_secret');
            $this->addFlash('success', 'Double authentification activee avec succes.');
            return $this->redirectToRoute('app_2fa_setup');
        }

        $enabled = $googleAuthenticatorService->isEnabled($user);
        $pendingSecret = (string) $request->getSession()->get('two_factor_pending_secret', '');

        if (!$enabled && $pendingSecret === '') {
            $pendingSecret = $googleAuthenticatorService->generateSecret();
            $request->getSession()->set('two_factor_pending_secret', $pendingSecret);
        }

        $secretToDisplay = $enabled ? $googleAuthenticatorService->getSecret($user) : $pendingSecret;
        $otpauthUrl = $secretToDisplay ? $googleAuthenticatorService->getProvisioningUri($user, $secretToDisplay) : null;
        $qrCodeUrl = $otpauthUrl
            ? 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($otpauthUrl)
            : null;

        return $this->render('Auth/two_factor_setup.html.twig', [
            'enabled' => $enabled,
            'secret' => $secretToDisplay,
            'otpauthUrl' => $otpauthUrl,
            'qrCodeUrl' => $qrCodeUrl,
        ]);
    }
}
