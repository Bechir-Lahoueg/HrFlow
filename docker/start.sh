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

# Fix permissions: cache:warmup creates files as root; PHP-FPM (www-data) needs write access
chmod -R 777 var/ public/uploads public/assets 2>/dev/null || true

# Run pending database migrations automatically
echo "==> Running database migrations..."
APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration 2>&1 || echo "!!! migrations FAILED !!!"

echo "==> Starting PHP-FPM + Nginx via Supervisor on port ${PORT}..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
