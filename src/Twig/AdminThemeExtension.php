<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\Shared\AdminThemeService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class AdminThemeExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly AdminThemeService $adminThemeService,
        private readonly Security $security,
    ) {
    }

    public function getGlobals(): array
    {
        $themes = $this->adminThemeService->getAll();

        try {
            $user = $this->security->getUser();
            $currentTheme = $this->adminThemeService->getTheme($user);
        } catch (\Throwable) {
            $currentTheme = $themes[AdminThemeService::DEFAULT_THEME];
        }

        return [
            'admin_theme'     => $currentTheme,
            'admin_theme_key' => $currentTheme['key'],
            'admin_themes'    => array_values($themes),
        ];
    }
}
