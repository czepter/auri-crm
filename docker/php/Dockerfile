FROM php:8.5-cli

# Composer resolves against THIS image's PHP rather than its own bundled one.
# The standalone composer:2 image ships a different runtime, and when those two
# drifted apart it resolved a Pest release the container could not run.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Matches the extension list CI installs via shivammathur/setup-php
# (pdo_pgsql, bcmath, gd, intl, exif) so a test that passes here passes there.
#
# pcntl is the odd one out: CI's setup-php action enables it by default, so it
# was never listed there, but this image builds PHP from source and needs it
# named explicitly. Horizon's master supervisor traps SIGTERM/SIGUSR2 through
# it to pause and terminate workers gracefully — without it the process can
# still boot but only ever dies hard.
#
# postgresql-client is the odd one out here too: nothing above it needs
# pg_dump/pg_restore to compile, only db:cutover-snapshot and
# db:cutover-restore (X-03) to run — the client tools, not libpq-dev's
# headers, are what those two Process calls actually shell out to.
#
# libheif-plugin-aomenc: Debian's libheif1 ships with no AV1 encoder linked
# in — heifsave fails "Unsupported compression" on every AVIF write without
# it, decode-only formats work fine either way.
#
# libvips-tools (G-03, #52) is the odd one out a third way: it's a CLI tool,
# not a PHP extension, so it never appears in setup-php's list either — CI's
# `studio` job installs the matching apt package directly, the same pattern
# X-03 set for postgresql-client. App\Media\Support\LibvipsDerivativePipeline
# shells out to vipsthumbnail/vipsheader rather than binding libvips into PHP
# (no ext-vips, no jcupitt/vips) — one CLI call per derivative is simple and
# the P3 gallery spike's own throughput numbers show it's nowhere near the
# bottleneck.
#
# zip/libzip-dev is a fourth: setup-php enables ext-zip by default, so CI never
# named it and BuildGalleryZipTest passed there while `new ZipArchive` fatalled
# in this image.
#
# The client is pinned to the server's own major (postgresql-client-18, from
# PGDG rather than Debian's 17) because pg_dump refuses a newer server
# outright — "server version mismatch", which is db:cutover-snapshot (X-03)
# failing before it writes a byte, not a warning.
RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        gnupg \
    && curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc \
        | gpg --dearmor -o /usr/share/keyrings/pgdg.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/pgdg.gpg] https://apt.postgresql.org/pub/repos/apt $(. /etc/os-release && echo "$VERSION_CODENAME")-pgdg main" \
        > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
        libavif-dev \
        libzip-dev \
        postgresql-client-18 \
        libvips-tools \
        libheif-plugin-aomenc \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql bcmath gd intl pcntl exif zip \
    && rm -rf /var/lib/apt/lists/*

# CI runs the suite under setup-php, which sets memory_limit=-1 — so the whole
# suite passed there while the same run in this image died on the AWS SDK's
# endpoints table at php-cli's 128M default. Matching CI is the point: a test
# that passes here passes there.
RUN printf 'memory_limit=-1\n' > /usr/local/etc/php/conf.d/zz-memory-limit.ini

# F-11: release tracking for GlitchTip (config/sentry.php's SENTRY_RELEASE)
# from the git SHA at build time, so an error is attributable to the deploy
# that shipped it. Empty by default — `docker compose build` never passes
# GIT_SHA locally, matching SENTRY_LARAVEL_DSN's own empty-by-default rule
# (docker-compose.yml). A real deploy pipeline sets it with
# `--build-arg GIT_SHA=$(git rev-parse --short HEAD)`.
ARG GIT_SHA=
ENV SENTRY_RELEASE=${GIT_SHA}
