# Version de démonstration en ligne de SalonFlow (Render, Railway, Fly.io…)
# La base SQLite est recréée avec des ventes fictives à chaque démarrage, puis chaque nuit à 3 h.
FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install opcache
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && chmod +x docker/demarrer-demo.sh

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SALON_DEMO=true \
    TRUSTED_PROXIES=* \
    PHP_CLI_SERVER_WORKERS=4 \
    PORT=8080

EXPOSE 8080
CMD ["docker/demarrer-demo.sh"]
