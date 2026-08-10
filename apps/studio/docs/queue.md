# Queue runtime

Redis-backed, supervised by [Horizon](https://laravel.com/docs/horizon), one
named queue per workload:

| Queue          | Supervisor                 | Timeout | Carries                                    |
|----------------|-----------------------------|---------|---------------------------------------------|
| `default`      | `supervisor-default`       | 60s     | Anything without a more specific home yet.  |
| `mail`         | `supervisor-mail`          | 30s     | Outbound notifications and transactional mail. |
| `render`       | `supervisor-render`        | 300s    | Document rendering (Gotenberg, M-01) and other slow external calls. |
| `media.ingest` | `supervisor-media-ingest`  | 20s     | Deriving thumbnails/previews from an uploaded original (G-01a). |
| `media.zip`    | `supervisor-media-zip`     | 2400s   | Building a gallery's ZIP download, streamed through the application (G-01a; G-10, #59). |

Nothing dispatches onto `mail`, `render` or `media.zip` yet — each story ships
the queue seam ahead of the job that will use it. `media.ingest` carries
`App\Media\Jobs\ProcessIngestedAsset` (G-03) — dispatched by
`MultipartUploadController::complete()` the moment a multipart upload session
is marked Completed; see docs/media.md's own "Ingest processing (G-03)"
section. Dispatch onto the queue that matches what the job does:

```php
SomeJob::dispatch($thing)->onQueue('render');
```

Supervisor definitions and their timeouts live in `config/horizon.php`
(`defaults`/`environments`), not in `config/queue.php` — a queue connection
has no per-queue granularity, only Horizon's supervisors do.

## Running it

`docker compose up` starts a `worker` container running `php artisan horizon`
alongside `php`, `postgres`, `redis`, `mailpit` and `minio` (G-01a's local
S3-compatible service — see docs/media.md). The dashboard is mounted
on the admin surface at `/horizon`, behind the `ManageStaff` capability (see
`App\Providers\HorizonServiceProvider` and
`InvitationController::authorizeStaffManagement()` for the sibling check) —
not behind an environment check, so a local Compose stack denies a session
without it exactly as production does.

Horizon's own package provider registers its routes unconditionally, so
`composer.json` disables its auto-discovery and `bootstrap/providers.php`
registers it by hand, admin surface only — the same "network-restricted,
enforced by absence" rule `config/surface.php` holds every other admin-only
route to.

## Failed jobs and retrying one

`failed_jobs` is Laravel's standard table (`0001_01_01_000002_create_jobs_table`
migration, alongside the renamed `queue_jobs` — see that migration's own
comment). A job that exhausts its `$tries` lands a row there with its
exception and payload.

Retry it from Horizon's Failed Jobs tab (calls the same `queue:retry`
mechanism under the hood), or from the CLI:

```sh
docker compose run --rm php php artisan queue:retry <uuid>
# or, for every failed job:
docker compose run --rm php php artisan queue:retry all
```

Retrying re-queues the job on its original connection and queue with a fresh
attempt count, and removes the `failed_jobs` row — a retry that fails again
gets its own new row rather than reusing the old one.

A failed job's own `payload` column carries a `requestId` key — the
`X-Request-Id` of the HTTP request that dispatched it (F-11,
`App\Providers\ObservabilityServiceProvider` stamps it on push,
`App\Support\Observability\JobRequestId` reads it back on failure). GlitchTip
tags the failure report with the same id, so "the client says the download
failed" is one query against a request id, not a guess at which of several
same-second jobs it was.

## Deploy

This repository has no production deploy configuration yet (no `fly.toml`,
`render.yaml`, Kubernetes manifests, or `deploy/` directory — checked at the
time this queue runtime was added) — `.github/workflows/ci.yml` runs tests
only. Wherever a deploy mechanism is added, it needs a worker process running
`php artisan horizon`, a reachable Redis, and `QUEUE_CONNECTION=redis` in that
environment's configuration, matching the `worker` service in
`docker-compose.yml`. Flagged here rather than guessed at, so it isn't
mistaken for a solved problem.
