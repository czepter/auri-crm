# syntax=docker/dockerfile:1

# The production image every PHP process in the Coolify stack runs: the three
# HTTP surfaces, Horizon, the scheduler and the migration one-shot all share it
# and differ only by APP_SURFACE and command. docker/php/Dockerfile stays the
# local development image — it bind-mounts the working tree and keeps
# require-dev. This one bakes the source in, drops require-dev and serves
# through FrankenPHP rather than `artisan serve`, which is single-threaded and
# was never meant to face a proxy.

# ---------------------------------------------------------------- base
# Pinned: a minor release can bump the bundled Mercure hub, which is how 1.13
# broke mercure.caddy. Keep it on the Mercure of the local hub in
# docker-compose.yml (v1.1.1).
FROM dunglas/frankenphp:1.13.2-php8.5.12 AS base

# install-php-extensions ships inside the FrankenPHP image and resolves each
# extension's system libraries itself, so the list here is the one CI tests
# against in the `ci` stage below (pdo_pgsql, bcmath, gd, intl, exif), plus pcntl —
# Horizon traps SIGTERM/SIGUSR2 through it, see docker/php/Dockerfile — plus
# opcache, which the dev image has no use for, and zip, which Composer needs to
# unpack a dist archive at all.
RUN install-php-extensions pdo_pgsql bcmath gd intl exif pcntl opcache zip \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'upload_max_filesize=12M\npost_max_size=14M\n' > "$PHP_INI_DIR/conf.d/zz-uploads.ini"

# Neither of these is a PHP extension, which is why install-php-extensions
# above cannot carry them and docker/php/Dockerfile installs them by apt too.
# App\Media\Support\LibvipsDerivativePipeline shells out to
# vipsthumbnail/vipsheader rather than binding libvips into PHP (#52), and
# Debian's libheif links no AV1 encoder on its own — without the plugin every
# AVIF rung of the derivative ladder fails "Unsupported compression" while the
# WebP rung beside it succeeds.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libvips-tools \
        libheif-plugin-aomenc \
    && rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------------- ci
# ci.yml's studio and e2e jobs run in this stage, so the suite runs on the
# runtime production serves. The jobs run as the runner's own uid to keep the
# workspace they share with the host jobs runner-owned, and that user cannot
# apt-get: everything a job used to install on the host is baked in here,
# Chromium pinned to the lockfile's Playwright through the build arg.
# memory_limit=-1 matches docker/php/Dockerfile; php.ini-production's 128M
# kills the suite on the AWS SDK's endpoints table.
FROM base AS ci
ARG PLAYWRIGHT_VERSION
ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=node:24-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && printf 'memory_limit=-1\n' > "$PHP_INI_DIR/conf.d/zz-memory-limit.ini" \
    && apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates curl gnupg git unzip \
    && curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc \
        | gpg --dearmor -o /usr/share/keyrings/pgdg.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/pgdg.gpg] https://apt.postgresql.org/pub/repos/apt $(. /etc/os-release && echo "$VERSION_CODENAME")-pgdg main" \
        > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update && apt-get install -y --no-install-recommends postgresql-client-18 \
    && npx -y playwright@${PLAYWRIGHT_VERSION} install --with-deps chromium \
    && chmod -R a+rX /ms-playwright \
    && rm -rf /var/lib/apt/lists/* /root/.npm

# ---------------------------------------------------------------- assets
FROM node:24-alpine AS assets

ENV COREPACK_ENABLE_DOWNLOAD_PROMPT=0
WORKDIR /build

# Manifests first so a source-only change reuses the install layer. The lockfile
# is the workspace root's — pnpm resolves apps/studio from there, not from its
# own directory.
# Filtered rather than a plain install: pnpm-workspace.yaml globs apps/* and
# packages/*, and this stage deliberately copies only the one manifest it
# builds from. Without the filter pnpm goes looking for every other workspace
# package's package.json and fails on the ones that are not in the context.
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
COPY apps/studio/package.json apps/studio/package.json
RUN corepack enable && pnpm install --frozen-lockfile --filter @studio/app...

COPY apps/studio apps/studio
# Both surface entrypoints (resources/js/{admin,portal}/main.ts) build into one
# public/build manifest; each container serves only the one its routes ask for.
RUN pnpm --filter @studio/app build

# ---------------------------------------------------------------- vendor
# Composer runs on THIS image's PHP rather than the composer image's bundled
# runtime, the same reason docker/php/Dockerfile copies the binary across: when
# the two drifted, dependency resolution picked releases the container couldn't
# run.
FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app

COPY apps/studio/composer.json apps/studio/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY apps/studio .
RUN composer dump-autoload --optimize --no-dev

# ---------------------------------------------------------------- app
FROM base AS app
WORKDIR /app

COPY --from=vendor /app .
COPY --from=assets /build/apps/studio/public/build ./public/build
COPY docker/app/entrypoint.sh /usr/local/bin/app-entrypoint
COPY docker/app/mercure.caddy /etc/frankenphp/mercure.caddy

# /data and /config are Caddy's state directories in the FrankenPHP image; an
# unprivileged server still has to be able to write them.
RUN chmod +x /usr/local/bin/app-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache /data /config

USER www-data

# Bare port, no host: this tells the bundled Caddyfile to serve plain HTTP and
# skip its automatic TLS. Coolify's proxy terminates TLS in front of it, and two
# certificate authorities for one hostname is a redirect loop waiting to happen.
ENV SERVER_NAME=:8080

# Everything the deployment cannot change lives here rather than in the Compose
# file. Coolify's parser rejects YAML anchors, so any variable left in Compose
# has to be repeated once per service — and a service name or a driver choice
# repeated eight times is eight places for them to drift apart. What remains in
# Compose is exactly the set that varies per deployment: secrets and domains.
#
# predis rather than config/database.php's phpredis default: composer.json
# requires predis/predis and install-php-extensions above installs no ext-redis,
# so leaving the default in place makes every Redis call fatal — the migration
# one-shot dies on `--isolated`'s cache lock and no surface ever starts.
ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_NAME=Studio \
    APP_LOCALE=de \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=warning \
    DB_CONNECTION=pgsql \
    DB_HOST=postgres \
    DB_PORT=5432 \
    REDIS_HOST=redis \
    REDIS_PORT=6379 \
    REDIS_CLIENT=predis \
    QUEUE_CONNECTION=redis \
    CACHE_STORE=redis \
    SESSION_DRIVER=database \
    SESSION_SECURE_COOKIE=true \
    FILESYSTEM_DISK=s3 \
    AWS_DEFAULT_REGION=us-east-1 \
    AWS_USE_PATH_STYLE_ENDPOINT=true \
    MAIL_MAILER=smtp \
    MAIL_FROM_NAME=Studio \
    SENTRY_ENVIRONMENT=production \
    GOTENBERG_URL=http://gotenberg:3000 \
    MERCURE_URL=http://admin:8080/.well-known/mercure

# F-11: baked rather than passed per deploy, because the release is a property
# of the image — GlitchTip needs it to say which build an error came from, and
# config/sentry.php reads it once at boot. The CI workflow supplies the commit.
ARG SENTRY_RELEASE=""
ENV SENTRY_RELEASE=$SENTRY_RELEASE

# Baked for the same reason, and it has to be: docker-compose.coolify.yml pins
# an image tag, so the version the app names and the image the stack pulls are
# the same artefact and cannot drift. The default is what a local `docker build`
# gets.
ARG APP_VERSION="dev"
ENV APP_VERSION=$APP_VERSION
EXPOSE 8080

HEALTHCHECK --interval=10s --timeout=3s --start-period=30s --retries=6 \
    CMD curl --fail http://localhost:8080/up || exit 1

ENTRYPOINT ["app-entrypoint"]
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
