FROM php:8.4-fpm

# --- System dependencies ---
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    fontconfig \
    xfonts-75dpi \
    xfonts-base \
    nginx \
    supervisor \
    gettext-base \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# --- wkhtmltopdf (manual install for Debian Bookworm) ---
RUN curl -fsSL https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-3/wkhtmltox_0.12.6.1-3.bookworm_amd64.deb \
        -o /tmp/wkhtmltox.deb \
    && apt-get update && apt-get install -y /tmp/wkhtmltox.deb \
    && rm /tmp/wkhtmltox.deb \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# --- PHP extensions ---
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        intl \
        gd \
        zip \
        opcache \
        exif \
        mbstring \
        xml

# --- Composer ---
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# --- Install PHP dependencies (cached layer) ---
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# --- Copy application source ---
COPY . .

# --- Composer post-install scripts ---
RUN composer run-script post-install-cmd --no-interaction || true

# --- Pre-download Tailwind CSS standalone binary (v3.4.17, linux-x64) ---
# var/ is gitignored so the binary never arrives from git; fetch it explicitly.
# We then run it DIRECTLY (bypasses Symfony kernel — no APP_SECRET/DB needed at build time).
RUN mkdir -p var/tailwind/v3.4.17 \
    && curl -fsSL \
        "https://github.com/tailwindlabs/tailwindcss/releases/download/v3.4.17/tailwindcss-linux-x64" \
        -o var/tailwind/v3.4.17/tailwindcss-linux-x64 \
    && chmod +x var/tailwind/v3.4.17/tailwindcss-linux-x64

# --- Build Tailwind CSS (direct binary, no Symfony kernel required) ---
RUN var/tailwind/v3.4.17/tailwindcss-linux-x64 \
        -c tailwind.config.js \
        -i assets/styles/app.css \
        -o var/tailwind/app.built.css \
        --minify

# --- Nginx: remove default site, add Render template ---
RUN rm -f /etc/nginx/sites-enabled/default /etc/nginx/conf.d/default.conf
COPY docker/nginx/render.conf.template /etc/nginx/conf.d/render.conf.template

# --- Supervisor config ---
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# --- Startup script ---
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

# --- Permissions (LAST — covers all files created above, including tailwind output) ---
# PHP-FPM (www-data) must be able to write var/cache, var/log, public/uploads at runtime
RUN mkdir -p var/cache var/log var/tailwind public/uploads public/assets \
    && chown -R www-data:www-data var/ public/uploads public/assets \
    && chmod -R 775 var/ public/uploads public/assets

# --- PHP-FPM pool: run workers as www-data (explicitly set, avoids inherited umask issues) ---
RUN { \
    echo '[www]'; \
    echo 'user = www-data'; \
    echo 'group = www-data'; \
    echo 'listen.owner = www-data'; \
    echo 'listen.group = www-data'; \
    echo 'clear_env = no'; \
    } > /usr/local/etc/php-fpm.d/zz-docker-override.conf

# Render routes external traffic to port 10000 by default
EXPOSE 10000

CMD ["/start.sh"]
