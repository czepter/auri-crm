FROM php:8.5-cli

# Composer resolves against THIS image's PHP rather than its own bundled one.
# The standalone composer:2 image ships a different runtime, and when those two
# drifted apart it resolved a Pest release the container could not run.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Matches the extension list CI installs via shivammathur/setup-php
# (pdo_pgsql, bcmath, gd, intl) so a test that passes here passes there.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql bcmath gd intl \
    && rm -rf /var/lib/apt/lists/*
