<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\Shared\AccessibilityPreferencesService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose une fonction Twig `accessibility_prefs()` qui retourne les
 * préférences d'accessibilité de l'utilisateur courant (ou les valeurs
 * par défaut si non connecté). Utilisée dans base.html.twig pour
 * appliquer les classes CSS dès le rendu (anti-flash).
 */
final class AccessibilityExtension extends AbstractExtension
{
    public function __construct(
        private readonly AccessibilityPreferencesService $prefs,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('accessibility_prefs', [$this, 'getPrefs']),
            new TwigFunction('accessibility_font_scales', [$this, 'getFontScales']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPrefs(): array
    {
        return $this->prefs->load($this->security->getUser());
    }

    /**
     * @return int[]
     */
    public function getFontScales(): array
    {
        return AccessibilityPreferencesService::ALLOWED_FONT_SCALES;
    }
}
