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

No production deploy configuration exists yet to run `schedule:run` from
(the same gap docs/queue.md flags for the `worker` process); flagged here
rather than guessed at.

## Ingest: app-proxied multipart upload (G-02)

`App\Media\Http\Controllers\MultipartUploadController` (routes/admin.php,
`mfa` group) is the only thing the admin browser ever talks to — it never
receives a storage path, bucket name or presigned URL, in either direction,
the same rule G-11 (#60) applies to downloads. Every request it handles is
relayed to the bucket server-side by `App\Media\Services\
MultipartUploadGateway`, which reaches past Flysystem to the raw
`Aws\S3\S3Client` for the same reason `ReapIncompleteMultipartUploads`
already does: Flysystem has no multipart-upload API of its own.

A chunk is streamed straight from the request's own `php://input` resource
to S3's `UploadPart` — never buffered, never spooled to local disk. That
resource is never seekable, so it can never be SigV4-hashed and rewound to
send; `UploadPart` sets `X-Amz-Content-Sha256: UNSIGNED-PAYLOAD` on the
built request directly (before the signer runs) rather than through the
SDK's own `v4-unsigned-body` signature option, which S3Client's own
resolved `signature_version` config blocks a per-command override from
reaching. `request_checksum_calculation` is set to `when_required` on the
`s3` disk for the same reason: the SDK's default opt-in trailer checksum
also hashes (and so seeks) the body up front, and would otherwise defeat
the same streaming requirement it exists to protect.

`App\Media\Models\MultipartUpload` (tenant-scoped, `HasPublicId`) is what a
returning tab resumes from — found by fingerprint (filename, size, the
browser's own `File.lastModified`), never by the storage key or `uploadId`
it was never given. It carries no record of which parts have landed: the
bucket is the source of truth for that (`ListParts`), read fresh on every
resume and again when a session completes, so nothing here can drift from
what the bucket actually holds. Its `key` sits under `quarantine/` —
`ReapIncompleteMultipartUploads::QUARANTINE_PREFIX` — exactly what that
reaper already expects to find and clean up if a session is ever abandoned.

G-03 is what promotes a completed quarantine object into a gallery asset;
this story only lands bytes there safely.

## Delivery

Clients never receive a storage path or a presigned URL — the application
proxies every download (G-11, #60 carries what that costs). This story does
not build that endpoint.

## Backups

Out of scope. The studio backs up its object storage independently; this
system builds no replication or second copy.
