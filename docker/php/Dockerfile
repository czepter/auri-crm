FROM php:8.5-cli

# Composer resolves against THIS image's PHP rather than its own bundled one.
# The standalone composer:2 image ships a different runtime, and when those two
# drifted apart it resolved a Pest release the container could not run.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Matches the extension list CI installs via shivammathur/setup-php
# (pdo_pgsql, bcmath, gd, intl) so a test that passes here passes there.
#
# pcntl is the odd one out: CI's setup-php action enables it by default, so it
# was never listed there, but this image builds PHP from source and needs it
# named explicitly. Horizon's master supervisor traps SIGTERM/SIGUSR2 through
# it to pause and terminate workers gracefully — without it the process can
# still boot but only ever dies hard.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql bcmath gd intl pcntl \
    && rm -rf /var/lib/apt/lists/*
