FROM php:8.4-cli

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
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# --- wkhtmltopdf ---
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
RUN APP_ENV=prod APP_SECRET=buildsecret composer run-script post-install-cmd --no-interaction || true

# --- Download Tailwind CSS binary and build CSS ---
# (manual curl is more reliable than letting the bundle download it at build time)
RUN mkdir -p var/tailwind/v3.4.17 \
    && curl -fsSL \
        "https://github.com/tailwindlabs/tailwindcss/releases/download/v3.4.17/tailwindcss-linux-x64" \
        -o var/tailwind/v3.4.17/tailwindcss-linux-x64 \
    && chmod +x var/tailwind/v3.4.17/tailwindcss-linux-x64 \
    && var/tailwind/v3.4.17/tailwindcss-linux-x64 \
        -c tailwind.config.js \
        -i assets/styles/app.css \
        -o var/tailwind/app.built.css \
        --minify

# --- Compile all assets to public/assets/ (fingerprinted, for prod serving) ---
# asset-map:compile reads var/tailwind/app.built.css to replace @tailwind directives
RUN APP_ENV=prod APP_SECRET=buildsecret php bin/console asset-map:compile --no-interaction

# --- Diagnostic: verify compiled assets are present ---
RUN echo '=== Compiled CSS files ===' && find public/assets -name '*.css' | head -10 && echo '=== importmap.json ===' && test -f public/assets/importmap.json && echo 'OK' || echo 'MISSING'

# --- Startup script ---
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

# --- Pre-create writable directories ---
RUN mkdir -p var/cache var/log var/tailwind public/uploads public/assets \
    && chmod -R 777 var/ public/uploads public/assets

EXPOSE 10000

CMD ["/start.sh"]
