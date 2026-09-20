#!/usr/bin/env bash
# Starts the admin surface for local development: Postgres + Redis + Gotenberg
# + Laravel (in Docker, per the project's "all PHP runs in Docker" rule) + Vite
# dev server for HMR. Ctrl+C stops both.
#
# The portal comes up alongside it as a plain compose service: it is a second
# surface on its own port, and links the admin mints into it (portal previews,
# magic links) 404 without it.
#
# The gallery needs three more, and each one fails silently on its own:
# rustfs holds the uploaded bytes, worker runs the media.ingest queue that turns
# them into assets, and gallery serves the address the studio hands a guest.
# Without worker an upload reports success and the pictures never appear — the
# jobs simply pile up in Redis; without gallery the guest link refuses the
# connection.
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

# rustfs-init creates the bucket; api is what the gallery viewer reads from.
docker compose up -d postgres redis gotenberg rustfs rustfs-init api portal worker gallery

docker rm -f "$CONTAINER" >/dev/null 2>&1 || true
# --no-reload keeps the compose environment reachable from a served request —
# see the portal service in docker-compose.yml.
docker compose run --rm --service-ports -e APP_SURFACE=admin -d --name "$CONTAINER" \
  php php artisan serve --host=0.0.0.0 --port=8000 --no-reload >/dev/null

echo "Admin:   http://localhost:8000"
echo "Portal:  http://localhost:${PORTAL_PORT:-8001}"
echo "Galerie: http://localhost:${GALLERY_PORT:-4321}"
pnpm --filter @studio/app dev
