#!/usr/bin/env bash
# Usage: factory/stop.sh <worktree>
# Stops everything factory/serve.sh and the gate started for a worktree.
set -euo pipefail

dir=$(cd "$1" && pwd)
cd "$dir"

if [ "$(git rev-parse --git-dir)" = "$(git rev-parse --git-common-dir)" ]; then
  echo "refusing: $dir is the main checkout, not a worktree" >&2
  exit 1
fi

docker rm -f "factory-$(basename "$dir")" >/dev/null 2>&1 || true
docker compose down --remove-orphans
