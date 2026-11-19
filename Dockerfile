# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.5
ARG NODE_VERSION=24

# -----------------------------------------------------------------------------
# Node toolchain (copied into the dev image, used to build assets in prod)
# -----------------------------------------------------------------------------
FROM node:${NODE_VERSION}-slim AS node

# -----------------------------------------------------------------------------
# Base: PHP-FPM + Nginx with the extensions this app needs
# Image docs: https://serversideup.net/open-source/docker-php/
# -----------------------------------------------------------------------------
FROM serversideup/php:${PHP_VERSION}-fpm-nginx AS base

USER root
# Defaults already include pdo_pgsql, redis, opcache, pcntl, zip, mbstring...
RUN install-php-extensions intl gd bcmath exif
USER www-data

# -----------------------------------------------------------------------------
# Development: adds Node so artisan, composer and Vite run in one container
# -----------------------------------------------------------------------------
FROM base AS development

USER root
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && node --version && npm --version
USER www-data

# =============================================================================
# Production: three build stages, only the last one is shipped.
#   docker build --target production -t zetago:latest .
# =============================================================================

# -----------------------------------------------------------------------------
# 1. PHP dependencies without dev packages (no PHPUnit, Pint, Faker...)
#    Only composer.json/lock are copied first, so this layer is cached until
#    the dependencies actually change.
# -----------------------------------------------------------------------------
FROM base AS vendor

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader \
    --prefer-dist --no-interaction --no-progress

# -----------------------------------------------------------------------------
# 2. Front-end build. Needs PHP too: the Wayfinder Vite plugin runs
#    `php artisan wayfinder:generate` to create the typed route helpers.
# -----------------------------------------------------------------------------
FROM development AS assets

COPY --chown=www-data:www-data package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor
RUN composer dump-autoload --no-dev --no-scripts \
    && npm run build

# -----------------------------------------------------------------------------
# 3. The shipped image: PHP + nginx, app code, vendor/, built assets.
#    No Node, no node_modules, no tests, no .env (secrets come from the
#    environment at runtime), runs as the non-root www-data user.
# -----------------------------------------------------------------------------
FROM base AS production

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    # Code never changes inside the image: never re-check file timestamps.
    PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    # serversideup start-up automation: config/route/view cache, storage
    # link. Migrations stay opt-in (AUTORUN_LARAVEL_MIGRATION) so only one
    # container runs them during a deploy.
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=false

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /var/www/html/public/build ./public/build

# Authoritative classmap: autoloader never touches the disk for unknown
# classes. package:discover writes bootstrap/cache/packages.php.
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction \
    && php artisan package:discover --ansi
