# Media storage (G-01a)

Object storage for galleries — originals, derivatives and ZIP builds — lives
on an S3-compatible bucket behind Laravel's `s3` disk (`config/filesystems.php`),
via Flysystem and the AWS SDK. Nothing else: no `StorageDriverInterface`, no
second abstraction on top of the one Flysystem already provides.

## The storage engine is undecided on purpose

`AWS_ENDPOINT` (plus `AWS_USE_PATH_STYLE_ENDPOINT`, `AWS_BUCKET`,
`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`) is all the
`s3` disk needs to point at any S3-compatible provider — self-hosted RustFS or
MinIO, IONOS Object Storage, Hetzner Object Storage. Nothing in `app/` names
one of these; only `docker-compose.yml`'s local `minio` service and its
env values do, because that service has to be something.

`docker compose up` starts `minio` (bitnami/minio) alongside `php` and
`worker`, with a `media` bucket created on first boot
(`MINIO_DEFAULT_BUCKETS`) and credentials set directly on the `php`/`worker`
services' `environment:` — the same pattern `docker-compose.yml` already uses
for `DB_HOST` and friends, so a plain `docker compose up` works without
touching `.env`.

## Prefix layout

Partitioned by project and gallery, never by date — a gallery's originals,
derivatives and ZIP builds all live under one prefix, so deleting a gallery
(OQ-5, #80: the studio owner deletes by hand, nothing expires on a timer) is
a single prefix delete. G-02 (#51) and later stories own the actual layout;
this story only reserves `quarantine/` (below).

## Queues

Two queues carry media work, each with its own Horizon supervisor and
timeout (`config/horizon.php`) — see docs/queue.md's table. A 20-second
thumbnail job and a 40-minute ZIP build sharing a worker pool would either
starve the thumbnail behind the ZIP or let a stuck thumbnail job hold a
worker four times longer than a legitimate ZIP build needs:

- `media.ingest` (20s) — deriving thumbnails/previews from an uploaded original.
- `media.zip` (2400s / 40min) — building a gallery's ZIP download. Streamed
  through the application rather than assembled with `CopyObject`/
  `UploadPartCopy` (G-10, #59) — that pair's S3-feature-set support varies
  across the providers this disk has to run on unmodified.

Nothing dispatches onto either yet; this story ships the queue seam, not the
jobs. See `tests/Feature/Queue/MediaQueuesTest.php`.

## Lifecycle: the multipart-upload reaper

The only thing that expires on a timer. An abandoned multipart upload is
billed storage no listing shows, so `php artisan media:reap-multipart-uploads`
(`App\Media\Console\Commands\ReapIncompleteMultipartUploads`) lists and aborts
anything left incomplete under the `quarantine/` prefix
(`ReapIncompleteMultipartUploads::QUARANTINE_PREFIX`) past `--older-than`
hours (default 24). Scheduled daily in `bootstrap/app.php`'s `withSchedule()`.

G-02 (#51) is what will actually start a multipart upload under
`quarantine/`; this story ships the cleanup side of that seam ahead of the
upload flow itself, deliberately — see #50.

No production deploy configuration exists yet to run `schedule:run` from
(the same gap docs/queue.md flags for the `worker` process); flagged here
rather than guessed at.

## Delivery

Clients never receive a storage path or a presigned URL — the application
proxies every download (G-11, #60 carries what that costs). This story does
not build that endpoint.

## Backups

Out of scope. The studio backs up its object storage independently; this
system builds no replication or second copy.
