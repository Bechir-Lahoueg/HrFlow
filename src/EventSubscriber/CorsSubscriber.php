<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Handles CORS for all routes.
 *
 * Allowed origins are configured centrally in config/packages/cors.yaml
 * via the CORS_ALLOW_ORIGINS environment variable (comma-separated list).
 * Both localhost and the Render deployment URL are listed there.
 */
final class CorsSubscriber implements EventSubscriberInterface
{
    /** @var list<string> */
    private array $allowedOrigins;

    public function __construct(
        string $corsAllowOrigins,
        private readonly string $corsAllowMethods,
        private readonly string $corsAllowHeaders,
        private readonly string $corsMaxAge,
    ) {
        $this->allowedOrigins = array_filter(array_map('trim', explode(',', $corsAllowOrigins)));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => ['onKernelRequest', 9999],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    /**
     * Handle pre-flight OPTIONS requests immediately — no controller needed.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($request->getMethod() !== 'OPTIONS') {
            return;
        }

        $response = new Response('', 204);
        $this->addCorsHeaders($request->headers->get('Origin') ?? '', $response);
        $event->setResponse($response);
    }

    /**
     * Attach CORS headers to every response when the origin is allowed.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $origin = $event->getRequest()->headers->get('Origin') ?? '';
        $this->addCorsHeaders($origin, $event->getResponse());
    }

    private function addCorsHeaders(string $origin, Response $response): void
    {
        if ($origin === '' || !in_array($origin, $this->allowedOrigins, true)) {
            return;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', $this->corsAllowMethods);
        $response->headers->set('Access-Control-Allow-Headers', $this->corsAllowHeaders);
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', $this->corsMaxAge);
        $response->headers->set('Vary', 'Origin');
    }
}
