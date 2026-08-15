#!/usr/bin/env bash
# Starts the admin surface for local development: Postgres + Redis + Gotenberg
# + Laravel (in Docker, per the project's "all PHP runs in Docker" rule) + Vite
# dev server for HMR. Ctrl+C stops both.
#
# The backing services are brought up with `compose up` rather than left to
# `compose run`'s depends_on: `run` will `start` a pre-existing container as-is,
# and a container that was stopped while its network got removed comes back with
# no network at all — healthy, but with `redis` no longer resolving.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")"

CONTAINER=studio-dev-server

cleanup() {
  docker stop "$CONTAINER" >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker compose up -d postgres redis gotenberg

docker rm -f "$CONTAINER" >/dev/null 2>&1 || true
docker compose run --rm --service-ports -e APP_SURFACE=admin -d --name "$CONTAINER" \
  php php artisan serve --host=0.0.0.0 --port=8000 >/dev/null

echo "Laravel: http://localhost:8000"
pnpm --filter @studio/app dev
