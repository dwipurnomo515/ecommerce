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
