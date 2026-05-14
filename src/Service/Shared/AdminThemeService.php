<?php

declare(strict_types=1);

namespace App\Service\Shared;

use App\Security\DbUser;
use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\User\UserInterface;

final class AdminThemeService
{
    public const DEFAULT_THEME = 'violet';

    /** Request-scoped cache: keyed by user id, value is the resolved theme key. */
    private array $cache = [];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, array{
     *     key: string, label: string, tagline: string,
     *     sidebar_gradient: string, sidebar_glow_from: string,
     *     banner_gradient: string,
     *     accent_500: string, accent_600: string, accent_300: string,
     *     badge_class: string, preview_class: string
     * }>
     */
    public function getAll(): array
    {
        return [
            'violet' => [
                'key'               => 'violet',
                'label'             => 'Violet Royal',
                'tagline'           => 'Ambiance executive premium',
                'sidebar_gradient'  => 'linear-gradient(to bottom, #2e1065 0%, #1e1b4b 55%, #020617 100%)',
                'sidebar_glow_from' => 'rgba(139, 92, 246, 0.20)',
                'banner_gradient'   => 'linear-gradient(135deg, #7c3aed 0%, #6366f1 55%, #3730a3 100%)',
                'accent_500'        => '#8b5cf6',
                'accent_600'        => '#7c3aed',
                'accent_300'        => '#c4b5fd',
                'badge_class'       => 'bg-violet-100 text-violet-700',
                'preview_class'     => 'from-violet-600 to-indigo-700',
            ],
            'ocean' => [
                'key'               => 'ocean',
                'label'             => 'Ocean Deep',
                'tagline'           => 'Bleu profond et apaisant',
                'sidebar_gradient'  => 'linear-gradient(to bottom, #082f49 0%, #0c4a6e 55%, #020617 100%)',
                'sidebar_glow_from' => 'rgba(56, 189, 248, 0.22)',
                'banner_gradient'   => 'linear-gradient(135deg, #0284c7 0%, #1d4ed8 55%, #1e3a8a 100%)',
                'accent_500'        => '#0ea5e9',
                'accent_600'        => '#0284c7',
                'accent_300'        => '#7dd3fc',
                'badge_class'       => 'bg-sky-100 text-sky-700',
                'preview_class'     => 'from-sky-600 to-blue-700',
            ],
            'emerald' => [
                'key'               => 'emerald',
                'label'             => 'Emerald Forest',
                'tagline'           => 'Vert naturel et equilibre',
                'sidebar_gradient'  => 'linear-gradient(to bottom, #022c22 0%, #064e3b 55%, #020617 100%)',
                'sidebar_glow_from' => 'rgba(52, 211, 153, 0.22)',
                'banner_gradient'   => 'linear-gradient(135deg, #059669 0%, #0d9488 55%, #065f46 100%)',
                'accent_500'        => '#10b981',
                'accent_600'        => '#059669',
                'accent_300'        => '#6ee7b7',
                'badge_class'       => 'bg-emerald-100 text-emerald-700',
                'preview_class'     => 'from-emerald-600 to-teal-700',
            ],
            'sunset' => [
                'key'               => 'sunset',
                'label'             => 'Sunset Fire',
                'tagline'           => 'Orange ardent, energie solaire',
                'sidebar_gradient'  => 'linear-gradient(to bottom, #431407 0%, #7c2d12 55%, #0c0a09 100%)',
                'sidebar_glow_from' => 'rgba(251, 146, 60, 0.22)',
                'banner_gradient'   => 'linear-gradient(135deg, #ea580c 0%, #db2777 55%, #9f1239 100%)',
                'accent_500'        => '#f97316',
                'accent_600'        => '#ea580c',
                'accent_300'        => '#fdba74',
                'badge_class'       => 'bg-orange-100 text-orange-700',
                'preview_class'     => 'from-orange-500 to-rose-600',
            ],
        ];
    }

    public function getTheme(?UserInterface $user): array
    {
        $key = $this->getThemeKey($user);
        $themes = $this->getAll();

        return $themes[$key] ?? $themes[self::DEFAULT_THEME];
    }

    public function getThemeKey(?UserInterface $user): string
    {
        if (!$user instanceof DbUser || $user->getSource() !== 'users') {
            return self::DEFAULT_THEME;
        }

        $userId = (int) $user->getId();

        if (isset($this->cache[$userId])) {
            return $this->cache[$userId];
        }

        try {
            $value = $this->connection->fetchOne(
                'SELECT admin_dashboard_theme FROM users WHERE id = :id LIMIT 1',
                ['id' => $userId],
            );
        } catch (\Throwable) {
            return self::DEFAULT_THEME;
        }

        if (!is_string($value) || $value === '') {
            return $this->cache[$userId] = self::DEFAULT_THEME;
        }

        return $this->cache[$userId] = array_key_exists($value, $this->getAll()) ? $value : self::DEFAULT_THEME;
    }

    public function save(int $userId, string $themeKey): bool
    {
        if (!array_key_exists($themeKey, $this->getAll())) {
            return false;
        }

        try {
            $this->connection->update(
                'users',
                ['admin_dashboard_theme' => $themeKey],
                ['id' => $userId],
            );
        } catch (\Throwable) {
            return false;
        }

        return true;
    }
}
