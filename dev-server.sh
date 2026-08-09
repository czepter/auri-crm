#!/usr/bin/env bash
# Starts the admin surface for local development: Postgres + Laravel (in
# Docker, per the project's "all PHP runs in Docker" rule) + Vite dev server
# for HMR. Ctrl+C stops both.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")"

CONTAINER=studio-dev-server

cleanup() {
  docker stop "$CONTAINER" >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker compose up -d postgres

docker rm -f "$CONTAINER" >/dev/null 2>&1 || true
docker compose run --rm --service-ports -e APP_SURFACE=admin -d --name "$CONTAINER" \
  php php artisan serve --host=0.0.0.0 --port=8000 >/dev/null

echo "Laravel: http://localhost:8000"
pnpm --filter @studio/app dev
