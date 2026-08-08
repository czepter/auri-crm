FROM php:8.3-cli

# Matches the extension list CI installs via shivammathur/setup-php
# (pdo_pgsql, bcmath, gd, intl) so a test that passes here passes there.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql bcmath gd intl \
    && rm -rf /var/lib/apt/lists/*
