#!/bin/sh
# The CI e2e job, replayed in a container: ubuntu-latest's distribution, the
# Chromium build the lockfile's Playwright pins, PHP from the PPA setup-php
# installs from, postgres and redis as CI's service containers. Screenshots
# taken on a Mac never match the runner (#324).
#
# ponytail: native architecture. Chromium segfaults under QEMU's amd64
# emulation on Apple silicon, so an arm64 host takes arm64 screenshots.
#
#   pnpm --filter @studio/app test:e2e:linux --project=visual --update-snapshots
set -eu

root=$(cd "$(dirname "$0")/../.." && pwd)
studio="$root/apps/studio"
playwright=$(node -p "require('$studio/node_modules/@playwright/test/package.json').version")
image="auri-e2e:$playwright"
run="auri-e2e-$$"

docker build -t "$image" - <<EOF
FROM mcr.microsoft.com/playwright:v$playwright-noble
RUN apt-get update \
 && apt-get install -y --no-install-recommends software-properties-common \
 && add-apt-repository -y ppa:ondrej/php \
 && apt-get install -y --no-install-recommends \
    php8.5-cli php8.5-pgsql php8.5-bcmath php8.5-gd php8.5-intl php8.5-mbstring \
    php8.5-xml php8.5-curl php8.5-zip php8.5-redis \
 && rm -rf /var/lib/apt/lists/*
EOF

# Built on the host: the bundles are platform-independent, node_modules is not.
pnpm --dir "$root" --filter @studio/app build
pnpm --dir "$root" --filter @studio/mcp build

trap 'docker rm -f "$run-pg" "$run-redis" >/dev/null 2>&1' EXIT

# One network namespace for all three, so 127.0.0.1 reaches the services the
# way it does on the runner and .env.example needs no host rewritten.
docker run -d --name "$run-pg" \
  -e POSTGRES_DB=studio_test -e POSTGRES_USER=studio -e POSTGRES_PASSWORD=studio \
  postgres:18-alpine >/dev/null
docker run -d --name "$run-redis" --network "container:$run-pg" redis:7-alpine >/dev/null
until docker exec "$run-pg" pg_isready -U studio >/dev/null 2>&1; do sleep 1; done

docker run --rm --network "container:$run-pg" \
  -v "$root:/app" -w /app/apps/studio -e CI=true -e APP_SURFACE=admin \
  "$image" sh -c '
    # CI copies .env.example to .env. Exported instead, so a developer'"'"'s own
    # .env stays untouched: Laravel never overwrites a variable already set.
    set -a
    . ./.env.example
    DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432
    DB_DATABASE=studio_test DB_USERNAME=studio DB_PASSWORD=studio
    APP_KEY=base64:$(openssl rand -base64 32)
    set +a
    node node_modules/@playwright/test/cli.js test "$@"
  ' e2e "$@"
