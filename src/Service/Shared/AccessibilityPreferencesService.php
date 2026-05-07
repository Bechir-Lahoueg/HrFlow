<?php

declare(strict_types=1);

namespace App\Service\Shared;

use App\Security\DbUser;
use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Charge / persiste les préférences d'accessibilité d'un utilisateur.
 *
 * Les préférences sont stockées dans une colonne JSON `accessibility_preferences`
 * sur les tables `users` et `employees` (selon DbUser::getSource()).
 *
 * Toutes les valeurs sont validées via une whitelist stricte pour éviter
 * toute injection ou stockage de valeurs invalides.
 */
final class AccessibilityPreferencesService
{
    /** Échelles de taille de texte autorisées (en %). */
    public const ALLOWED_FONT_SCALES = [90, 100, 115, 130, 150];

    /** Codes de langue autorisés pour la synthèse vocale (Web Speech API). */
    public const ALLOWED_VOICE_LANGS = ['fr-FR', 'en-US', 'ar-TN'];

    /** Vitesses de lecture autorisées (multiplieur, ex. 1.0 = normale). */
    public const ALLOWED_VOICE_SPEEDS = [0.75, 1.0, 1.25, 1.5];

    /**
     * Memoization par requête — évite de re-requêter la DB à chaque
     * appel Twig de accessibility_prefs() au sein de la même requête HTTP.
     * Clé : "source_userId" (ex. "employees_42").
     *
     * @var array<string, array<string, mixed>>
     */
    private array $requestCache = [];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Préférences par défaut (utilisées si aucune sauvegarde existante).
     *
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        return [
            'high_contrast'   => false,
            'font_scale'      => 100,
            'voice_feedback'  => false,
            'hover_reading'   => false,
            'voice_speed'     => 1.0,
            'simplified_ui'   => false,
            'reduce_motion'   => false,
            'voice_lang'      => 'fr-FR',
            'updated_at'      => null,
        ];
    }

    /**
     * Charge les préférences pour l'utilisateur courant.
     * Fusionne avec les valeurs par défaut → toutes les clés sont toujours présentes.
     *
     * @return array<string, mixed>
     */
    public function load(?UserInterface $user): array
    {
        $defaults = $this->getDefaults();

        if (!$user instanceof DbUser) {
            return $defaults;
        }

        // Memoize per request: return cached result without hitting the DB again.
        $cacheKey = $user->getSource() . '_' . $user->getId();
        if (isset($this->requestCache[$cacheKey])) {
            return $this->requestCache[$cacheKey];
        }

        $table = $user->getSource() === 'employees' ? 'employees' : 'users';

        try {
            $raw = $this->connection->fetchOne(
                sprintf('SELECT accessibility_preferences FROM %s WHERE id = :id LIMIT 1', $table),
                ['id' => $user->getId()],
            );
        } catch (\Throwable) {
            // Colonne potentiellement absente : fallback silencieux sur les défauts.
            return $defaults;
        }

        if (!is_string($raw) || $raw === '') {
            return $defaults;
        }

        try {
            $decoded = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $defaults;
        }

        if (!is_array($decoded)) {
            return $defaults;
        }

        $result = $this->sanitize(array_merge($defaults, $decoded));
        $this->requestCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Sauvegarde les préférences pour l'utilisateur courant.
     * Les valeurs sont sanitizées avant stockage.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed> Les préférences finales sauvegardées (ou null si échec).
     */
    public function save(?UserInterface $user, array $input): ?array
    {
        if (!$user instanceof DbUser) {
            return null;
        }

        $current = $this->load($user);
        $merged  = $this->sanitize(array_merge($current, $input));
        $merged['updated_at'] = (new \DateTimeImmutable())->format(DATE_ATOM);

        $table = $user->getSource() === 'employees' ? 'employees' : 'users';

        try {
            $this->connection->update(
                $table,
                ['accessibility_preferences' => json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                ['id' => $user->getId()],
            );
        } catch (\Throwable) {
            return null;
        }

        // Invalidate request-level cache so the next load() call reflects the saved value.
        $cacheKey = $user->getSource() . '_' . $user->getId();
        $this->requestCache[$cacheKey] = $merged;

        return $merged;
    }

    /**
     * Whitelist stricte : tout ce qui n'est pas reconnu retombe sur la valeur par défaut.
     *
     * @param array<string, mixed> $prefs
     * @return array<string, mixed>
     */
    private function sanitize(array $prefs): array
    {
        $defaults = $this->getDefaults();

        $fontScale = (int) ($prefs['font_scale'] ?? $defaults['font_scale']);
        if (!in_array($fontScale, self::ALLOWED_FONT_SCALES, true)) {
            $fontScale = $defaults['font_scale'];
        }

        $voiceLang = (string) ($prefs['voice_lang'] ?? $defaults['voice_lang']);
        if (!in_array($voiceLang, self::ALLOWED_VOICE_LANGS, true)) {
            $voiceLang = $defaults['voice_lang'];
        }

        $voiceSpeed = (float) ($prefs['voice_speed'] ?? $defaults['voice_speed']);
        if (!in_array($voiceSpeed, self::ALLOWED_VOICE_SPEEDS, true)) {
            $voiceSpeed = $defaults['voice_speed'];
        }

        return [
            'high_contrast'  => $this->toBool($prefs['high_contrast'] ?? false),
            'font_scale'     => $fontScale,
            'voice_feedback' => $this->toBool($prefs['voice_feedback'] ?? false),
            'hover_reading'  => $this->toBool($prefs['hover_reading'] ?? false),
            'voice_speed'    => $voiceSpeed,
            'simplified_ui'  => $this->toBool($prefs['simplified_ui'] ?? false),
            'reduce_motion'  => $this->toBool($prefs['reduce_motion'] ?? false),
            'voice_lang'     => $voiceLang,
            'updated_at'     => is_string($prefs['updated_at'] ?? null) ? $prefs['updated_at'] : null,
        ];
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
        }
        return (bool) $value;
    }
}
