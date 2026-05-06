<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class SecuritySubscriber implements EventSubscriberInterface
{
    public function __construct(
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    /**
     * Store user role in session on login for logout redirect
     */
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $token = $event->getAuthenticatedToken();
        $user = $token->getUser();
        $request = $event->getRequest();
        
        if ($user) {
            $roles = $token->getRoleNames();
            $session = $request->getSession();
            
            // Store primary role for logout redirect
            if (in_array('ROLE_CANDIDATE', $roles)) {
                $session->set('last_user_role', 'ROLE_CANDIDATE');
            } elseif (in_array('ROLE_ADMIN', $roles)) {
                $session->set('last_user_role', 'ROLE_ADMIN');
            } elseif (in_array('ROLE_RH', $roles)) {
                $session->set('last_user_role', 'ROLE_RH');
            } elseif (in_array('ROLE_EMPLOYEE', $roles)) {
                $session->set('last_user_role', 'ROLE_EMPLOYEE');
            }
        }
    }

    /**
     * Add cache control headers to prevent back-button access after logout
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $request = $event->getRequest();
        
        // Only apply to HTML responses
        if (!str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
            return;
        }
        
        // Add cache control headers to prevent caching of authenticated pages
        // This ensures back button doesn't show protected content after logout
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('Surrogate-Control', 'no-store');
        
        // Additional security headers
        $response->headers->set('Vary', 'Cookie');
    }
}
