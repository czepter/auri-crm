#!/usr/bin/env bash
# Usage: factory/serve.sh <worktree> <port-base>
# Starts the admin surface of a factory worktree on <port-base> with a freshly
# seeded database and built assets. Every compose port of the worktree moves
# into <port-base>..<port-base>+11 so several jobs and the main dev stack can
# run side by side. Last line of output is the URL.
set -euo pipefail

dir=$(cd "$1" && pwd)
base=$2
cd "$dir"

# migrate:fresh below wipes the compose project's database; the main checkout's
# project is the developer's dev database.
if [ "$(git rev-parse --git-dir)" = "$(git rev-parse --git-common-dir)" ]; then
  echo "refusing: $dir is the main checkout, not a worktree" >&2
  exit 1
fi

if ! command -v pnpm >/dev/null; then
  pnpm_bin=$(ls "$HOME"/.nvm/versions/node/*/bin/pnpm 2>/dev/null | sort -V | tail -1)
  export PATH="$PATH:$(dirname "${pnpm_bin:-/}")"
fi

ports=(APP PORTAL API GALLERY POSTGRES REDIS RUSTFS RUSTFS_CONSOLE GOTENBERG MERCURE MERCURE_PORTAL GLITCHTIP)
touch .env
grep -v -E "^($(IFS='|'; echo "${ports[*]}"))_PORT=" .env > .env.tmp || true
for i in "${!ports[@]}"; do
  echo "${ports[$i]}_PORT=$((base + i))" >> .env.tmp
done
mv .env.tmp .env

[ -f apps/studio/.env ] || cp apps/studio/.env.example apps/studio/.env
[ -d node_modules ] || pnpm install --frozen-lockfile
[ -d apps/studio/vendor ] || docker compose run --rm php composer install
grep -q '^APP_KEY=.\+' apps/studio/.env || docker compose run --rm php php artisan key:generate

pnpm --filter @studio/app build
rm -f apps/studio/public/hot

docker compose up -d postgres redis gotenberg rustfs rustfs-init mercure mercure-portal
docker compose run --rm -e APP_SURFACE=admin php php artisan migrate:fresh --seed --force

container="factory-$(basename "$dir")"
docker rm -f "$container" >/dev/null 2>&1 || true
docker compose run --rm --service-ports -d --name "$container" \
  -e APP_SURFACE=admin -e APP_URL="http://localhost:$base" \
  php php artisan serve --host=0.0.0.0 --port=8000 --no-reload >/dev/null

for _ in $(seq 60); do
  if [ "$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:$base/login")" = 200 ]; then
    echo "Sign in with the Dev-Login button on /login (dev@studio.test)."
    echo "http://localhost:$base"
    exit 0
  fi
  sleep 1
done
docker logs --tail 40 "$container" >&2
exit 1
