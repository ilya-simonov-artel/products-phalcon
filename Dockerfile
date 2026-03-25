

# --- Stage 1: frontend build ---
FROM node:lts AS frontend
WORKDIR /app
COPY package.json package-lock.json* vite.config.ts ./
COPY src ./src
COPY public ./public
RUN npm ci || npm install
RUN npm run build

# --- Stage 2: composer install & php ---
FROM phalconphp/cphalcon:v5.9.2-php8.4
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
# Кэшируем зависимости
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-dev --prefer-dist \
    && test -f /var/www/html/vendor/autoload.php
COPY . . --chown=phalcon:phalcon

# Копируем только ассеты и manifest.json из фронта
COPY --from=frontend --chown=phalcon:phalcon /app/public/dist/assets ./public/dist/assets
COPY --from=frontend --chown=phalcon:phalcon /app/public/dist/.vite/manifest.json ./public/dist/manifest.json


COPY --chmod=+x docker/entrypoint.sh /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/router.php"]
