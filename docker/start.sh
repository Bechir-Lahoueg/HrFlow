#!/bin/bash
set -e

cd /var/www/html

# Render sets PORT env var (defaults to 10000)
export PORT=${PORT:-10000}

# Inject $PORT into the nginx config (only $PORT is substituted, nginx vars are preserved)
envsubst '$PORT' < /etc/nginx/conf.d/render.conf.template > /etc/nginx/conf.d/default.conf

# Warm up Symfony production cache
echo "==> Warming up Symfony cache..."
APP_ENV=prod php bin/console cache:warmup --no-interaction || true

# Run pending database migrations automatically
echo "==> Running database migrations..."
APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true

echo "==> Starting PHP-FPM + Nginx via Supervisor on port ${PORT}..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
