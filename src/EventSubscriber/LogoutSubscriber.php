<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LogoutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RouterInterface $router
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        $session = $request->getSession();
        
        // Get the user's role before the token is cleared
        $token = $event->getToken();
        $roles = $token ? $token->getRoleNames() : [];
        
        // Also check referer for additional context
        $referer = $request->headers->get('referer', '');
        
        // Determine redirect based on role
        $targetRoute = 'app_login'; // Default for admin/RH/employee
        
        // Check if candidate role
        if (in_array('ROLE_CANDIDATE', $roles) || str_contains($referer, '/candidat')) {
            $targetRoute = 'app_candidate_login';
        }
        
        // Set the redirect response
        $response = new RedirectResponse($this->router->generate($targetRoute));
        $event->setResponse($response);
        
        // Invalidate session and clear cookies
        $session->invalidate();
        
        // Clear site data headers for extra security
        $response->headers->set('Clear-Site-Data', '"cookies", "storage"');
    }
}
