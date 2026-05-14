#!/bin/bash
set -e

cd /var/www/html

export PORT=${PORT:-10000}

echo "==> PHP version: $(php -r 'echo PHP_VERSION;')"
echo "==> APP_ENV=${APP_ENV:-not set}"

# Verify compiled assets exist (debug)
echo "==> Compiled CSS files in public/assets:"
find public/assets -name '*.css' 2>/dev/null | head -10 || echo '  (none found)'

# Warm up Symfony cache
echo "==> Warming up Symfony cache..."
APP_ENV=prod php bin/console cache:warmup --no-interaction

# Run pending migrations
echo "==> Running database migrations..."

# These migrations were already applied manually in prod (recorded with the old
# namespace format "DoctrineMigrationsVersionXXX" without backslash).
# We register them with the canonical format so Doctrine does not try to run them again.
SKIP_MIGRATIONS=(
    "DoctrineMigrations\\Version20260504120000"
    "DoctrineMigrations\\Version20260504123000"
    "DoctrineMigrations\\Version20260506180808"
    "DoctrineMigrations\\Version20260507120000"
    "DoctrineMigrations\\Version20260507192436"
)
for version in "${SKIP_MIGRATIONS[@]}"; do
    APP_ENV=prod php bin/console doctrine:migrations:version --add --no-interaction "$version" 2>/dev/null || true
done

APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Ensure var/ is fully writable (cache:warmup writes as same user — no issue here)
chmod -R 777 var/ public/uploads public/assets 2>/dev/null || true

echo "==> Starting PHP built-in server on port ${PORT}..."
exec php -S "0.0.0.0:${PORT}" -t public /var/www/html/docker/router.php
