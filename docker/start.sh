#!/bin/bash
set -e

cd /var/www/html

# Render sets PORT env var (defaults to 10000)
export PORT=${PORT:-10000}

# Inject $PORT into the nginx config (only $PORT is substituted, nginx vars are preserved)
envsubst '$PORT' < /etc/nginx/conf.d/render.conf.template > /etc/nginx/conf.d/default.conf

# Show PHP/Symfony version for debug
echo "==> PHP version: $(php -r 'echo PHP_VERSION;')"
echo "==> APP_ENV=${APP_ENV:-not set}"
echo "==> APP_SECRET present: $([ -n "$APP_SECRET" ] && echo YES || echo NO)"
echo "==> DATABASE_URL present: $([ -n "$DATABASE_URL" ] && echo YES || echo NO)"

# Warm up Symfony production cache — surface real errors
echo "==> Warming up Symfony cache..."
if ! APP_ENV=prod php bin/console cache:warmup --no-interaction 2>&1; then
    echo "!!! cache:warmup FAILED - continuing anyway, check env vars above !!!"
fi

# Run pending database migrations automatically
echo "==> Running database migrations..."
APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration 2>&1 || echo "!!! migrations FAILED !!!"

# Fix permissions LAST: cache:warmup + migrations both run as root and create files;
# PHP-FPM workers (www-data) must be able to write var/ at request time.
chown -R www-data:www-data var/ public/uploads public/assets 2>/dev/null || true

echo "==> Starting PHP-FPM + Nginx via Supervisor on port ${PORT}..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
