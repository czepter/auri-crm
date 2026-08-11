#!/bin/sh
set -e

# Coolify only generates a SERVICE_* variable when it is the *whole* value of an
# environment entry (bootstrap/helpers/shared.php: `$value?->startsWith('$')`),
# so the Compose file cannot write `base64:${SERVICE_REALBASE64_APPKEY}` — that
# entry is skipped and APP_KEY arrives as the literal prefix. The generated
# value is the base64 of 32 random bytes, which is what the cipher wants; only
# the marker Laravel reads it by is missing, so it goes on here.
case "${APP_KEY:-}" in
    '' | base64:*) ;;
    *) APP_KEY="base64:${APP_KEY}"; export APP_KEY ;;
esac

# No `php artisan config:cache` here, and it is not an oversight: config/sentry.php
# holds `new ScrubSensitiveData` (F-11's before_send hook), and the cache is a
# var_export of the config array — an object in it fails the command outright
# with "the value at sentry.before_send is non-serializable". Caching would also
# have had to happen here rather than at build time, since Coolify injects every
# secret at container start.
#
# ponytail: config is parsed per request instead, which opcache makes cheap
# enough to not be the thing to fix first. To get the cache back, before_send
# has to become something var_export can write — a static method reference
# rather than an instance — which is a change to F-11's code and its tests.

# The base image rewrote docker-php-entrypoint's `php` into `frankenphp run`, so
# this hands the server its flags unchanged and still execs `php artisan ...`
# verbatim for the worker, scheduler and migration containers.
exec docker-php-entrypoint "$@"
