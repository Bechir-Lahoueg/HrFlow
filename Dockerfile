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

# --- Permissions ---
RUN mkdir -p var/cache var/log public/uploads \
    && chmod -R 777 var/ public/uploads

# --- Composer post-install scripts ---
RUN composer run-script post-install-cmd --no-interaction || true

# --- Build Tailwind CSS ---
RUN APP_ENV=prod php bin/console tailwind:build --no-interaction

# --- Nginx: remove default site, add Render template ---
RUN rm -f /etc/nginx/sites-enabled/default /etc/nginx/conf.d/default.conf
COPY docker/nginx/render.conf.template /etc/nginx/conf.d/render.conf.template

# --- Supervisor config ---
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# --- Startup script ---
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

# Render routes external traffic to port 10000 by default
EXPOSE 10000

CMD ["/start.sh"]
