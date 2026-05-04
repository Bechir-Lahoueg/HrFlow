FROM php:8.2-fpm

# --- Dépendances système ---
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
    # wkhtmltopdf pour knp-snappy
    wkhtmltopdf \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# --- Extensions PHP ---
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

# --- Répertoire de travail ---
WORKDIR /var/www/html

# --- Copie des fichiers de dépendances en premier (cache Docker) ---
COPY composer.json composer.lock ./

# --- Installation des dépendances PHP (sans les dev en prod) ---
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# --- Copie du reste du code source ---
COPY . .

# --- Permissions sur var/ (cache, logs) ---
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var/ \
    && chmod -R 775 var/

# --- Exécution des scripts Composer post-install ---
RUN composer run-script post-install-cmd --no-interaction || true

EXPOSE 9000
CMD ["php-fpm"]
