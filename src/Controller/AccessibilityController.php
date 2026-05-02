<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Shared\AccessibilityPreferencesService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoints JSON pour synchroniser les préférences d'accessibilité
 * entre le client (localStorage) et le serveur (DB).
 *
 * Le panneau d'accessibilité applique les changements immédiatement côté
 * client (UX), puis appelle ces endpoints en arrière-plan pour persister.
 */
#[Route('/account/accessibility')]
final class AccessibilityController extends AbstractController
{
    public function __construct(
        private readonly AccessibilityPreferencesService $prefs,
    ) {
    }

    /**
     * Lecture des préférences courantes (utile pour resync après login
     * ou après changement de navigateur).
     */
    #[Route('', name: 'app_a11y_get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function get(): JsonResponse
    {
        return new JsonResponse([
            'ok'    => true,
            'prefs' => $this->prefs->load($this->getUser()),
        ]);
    }

    /**
     * Sauvegarde des préférences. Reçoit un JSON dans le body.
     * Le CSRF est validé via le header X-CSRF-Token.
     */
    #[Route('/save', name: 'app_a11y_save', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function save(Request $request): JsonResponse
    {
        $token = (string) $request->headers->get('X-CSRF-Token', '');
        if (!$this->isCsrfTokenValid('a11y_prefs', $token)) {
            return new JsonResponse([
                'ok'    => false,
                'error' => 'Jeton CSRF invalide.',
            ], 403);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse([
                'ok'    => false,
                'error' => 'Corps de requête JSON invalide.',
            ], 400);
        }

        $saved = $this->prefs->save($this->getUser(), $payload);
        if ($saved === null) {
            return new JsonResponse([
                'ok'    => false,
                'error' => 'Impossible d\'enregistrer les préférences.',
            ], 500);
        }

        return new JsonResponse([
            'ok'    => true,
            'prefs' => $saved,
        ]);
    }
}
