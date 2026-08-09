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

## Ingest processing (G-03)

`App\Media\Http\Controllers\MultipartUploadController::complete()` dispatches
`App\Media\Jobs\ProcessIngestedAsset` onto `media.ingest` the moment a session
is marked Completed. The job is a thin `ShouldQueue` wrapper; every actual
step lives in `App\Media\Actions\IngestCompletedUpload`, kept separate so the
whole pipeline is callable — and tested — without a queue round-trip:

1. Streams the quarantine object into a local temp file while computing its
   SHA-256 a chunk at a time (never the whole object in one PHP string).
2. Looks up an existing `Asset` by `(tenant, gallery, checksum)`. A match
   means these exact bytes already landed here — the quarantine copy is
   deleted and the `MultipartUpload` points at the existing asset, without
   redoing any of the steps below. This is what makes the whole pipeline
   idempotent on SHA-256: a retried job, or the same file uploaded twice,
   both converge on one row.
3. Reads dimensions via `vipsheader` (any format libvips can decode), then
   EXIF via PHP's built-in `exif_read_data()` — JPEG/TIFF only; every other
   format simply carries no `taken_at`/`camera_make`/`camera_model`. No
   composer package exists for this, and ext-exif already ships in
   `docker/php/Dockerfile` and CI's `shivammathur/setup-php` extension list.
4. Generates the derivative ladder — 400/800/1600/2560px, AVIF (`effort=2`)
   + WebP each — via `App\Media\Support\LibvipsDerivativePipeline`, which
   shells out to `vipsthumbnail`/`vipsheader` (`libvips-tools`, no PHP
   binding). Clamped downward for a source narrower than a rung, never
   upscaled. Chained from a single decode: each rung resizes down from the
   previous rung's own output rather than re-decoding the original, per the
   P3 gallery spike's own measurement (1.8x cheaper). Every call pins
   `--vips-concurrency=1` — concurrency comes from Horizon's
   `supervisor-media-ingest` worker count, never from libvips threading
   inside one job.
5. Computes a perceptual hash (`App\Media\Support\PerceptualHash`, a
   hand-rolled 32x32 DCT-based pHash — no composer package existed for this
   either) and a blurhash placeholder (`kornrunner/blurhash`, the one new
   composer dependency this story adds — small, single-purpose, no
   extensions beyond what's already required).
6. Moves the original from `quarantine/` to
   `galleries/{tenant_id}/{gallery_public_id}/originals/{asset_public_id}/`
   and uploads each derivative under the sibling `derivatives/` prefix —
   G-01a's reserved "partitioned by project and gallery" layout. Writes the
   `Asset` row (tenant-scoped, `HasPublicId`, `status: ready`) and every
   `AssetDerivative` row, then marks the `MultipartUpload` `processed_at`.

A corrupt file or a format libvips cannot decode never throws past the job:
it's caught and written as `Asset::status = failed` with an `error` message,
the original left untouched in quarantine for a human to inspect. This is
what G-04's "needs attention" tray will query — a Failed asset is an expected
outcome here, not a Horizon failed-job.

## ZIP builds (G-10)

`App\Media\Models\ZipBuild` (tenant-scoped, `HasPublicId`) is one deduped
archive for a `(gallery_version_id, scope, size)` triple — the migration's
own unique index is the dedupe mechanism; `App\Media\Actions\RequestZipBuild`
finds-or-creates against it and only dispatches
`App\Media\Jobs\BuildZip` (`media.zip`, #59) for a row that isn't already
Ready or already Building. `scope` has one case today (`all`); `size` is
`original` (each `Asset`'s own bytes) or `web` (each asset's widest WebP
derivative from G-03 — the one of its two formats every target platform can
at least open). A `ZipBuild` has one or more `ZipBuildPart` rows, the
`Asset`/`AssetDerivative` one-to-many precedent, each one archive volume's
output object key.

`App\Media\Actions\BuildGalleryZip` does the actual assembly — S3 to S3,
same "no server-side copy" rule as everywhere else in this doc: each member
is read from the `s3` disk and written straight into a
[zipstream-php](https://github.com/maennchen/zipstream-php) archive (STORE,
not DEFLATE — photos are already compressed; ZIP64 explicitly disabled, the
interoperability floor across Explorer, Finder, iOS Files and Files by
Google), whose own output is a callback stream that
`App\Media\Services\MultipartZipUploader` buffers to fixed 16MiB chunks and
uploads back to S3 via `MultipartUploadGateway`'s existing `UploadPart` seam
(G-02) as it fills. Nothing ever holds the whole archive — peak memory stays
near that 16MiB buffer. `App\Media\Support\ZipVolumePlanner` decides, before
any of that I/O, how members group into volumes so none would cross 3.9GiB
(a member already past that size gets a volume of its own rather than being
split) — pure grouping logic, unit-tested in isolation with a small injected
threshold rather than a multi-gigabyte fixture.

## Delivery

Clients never receive a storage path or a presigned URL — the application
proxies every download (G-11, #60 carries what that costs). This story does
not build that endpoint.

## Backups

Out of scope. The studio backs up its object storage independently; this
system builds no replication or second copy.
