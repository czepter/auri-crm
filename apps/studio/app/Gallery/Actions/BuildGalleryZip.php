<?php

namespace App\Gallery\Actions;

use App\Gallery\Models\GalleryList;
use App\Gallery\Models\GalleryVersion;
use App\Media\Enums\AssetStatus;
use App\Media\Enums\ZipBuildSize;
use App\Media\Enums\ZipBuildStatus;
use App\Media\Models\Asset;
use App\Media\Models\ZipBuild;
use App\Media\Models\ZipBuildPart;
use App\Media\Services\MultipartUploadGateway;
use App\Media\Services\MultipartZipUploader;
use App\Media\Support\ZipBuildMember;
use App\Media\Support\ZipVolumePlanner;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipStream\CompressionMethod;
use ZipStream\Stream\CallbackStreamWrapper;
use ZipStream\ZipStream;

/**
 * G-10 (#59): builds a ZipBuild's archive(s) — streamed through the
 * application, S3 to S3, never spooled to local disk. Every member is read
 * from the `s3` disk a chunk at a time and written straight into a
 * ZipStream, whose own output is wired to a callback stream
 * (ZipStream\Stream\CallbackStreamWrapper) that App\Media\Services\
 * MultipartZipUploader buffers to fixed 16MiB parts and multipart-uploads
 * back to S3 as it fills. Peak memory stays near that 16MiB buffer, never
 * the archive size — the P3 spike measured 23.8MB peak RSS for this same
 * store-mode assembly shape.
 *
 * ZIP64 is explicitly disabled (interoperability floor across Explorer,
 * Finder, iOS Files, Files by Google — #59) and STORE, not DEFLATE, is the
 * compression method: photos are already compressed, so DEFLATE would only
 * spend CPU for no size win.
 *
 * No server-side CopyObject/UploadPartCopy: that pair's support varies too
 * much across the self-hosted/managed S3-compatible backends this app must
 * run on (#50) to depend on, so the archive is assembled by reading and
 * rewriting bytes through the application instead.
 */
class BuildGalleryZip
{
    /** 3.9GiB — the split boundary #59 sets as the interoperability floor for a single archive volume. */
    public const SPLIT_THRESHOLD_BYTES = 4_187_593_113;

    public function __construct(private readonly MultipartUploadGateway $gateway) {}

    public function __invoke(ZipBuild $zipBuild, int $splitThresholdBytes = self::SPLIT_THRESHOLD_BYTES): void
    {
        if ($zipBuild->status !== ZipBuildStatus::Building) {
            // Already concluded (Ready) or already being (re)built by
            // another attempt — never redo finished or in-flight work.
            return;
        }

        // A retry of a previously Failed build: stale parts from the dead
        // attempt point at incomplete or nonexistent uploads, so they're
        // cleared before this attempt writes its own.
        $zipBuild->parts()->delete();

        $members = $this->resolveMembers($zipBuild->galleryVersion, $zipBuild->size, $zipBuild->galleryList);
        $volumes = ZipVolumePlanner::plan($members, $splitThresholdBytes);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('s3');

        try {
            foreach ($volumes as $index => $volumeMembers) {
                $this->buildVolume($zipBuild, $disk, $volumeMembers, $index + 1);
            }

            $zipBuild->update(['status' => ZipBuildStatus::Ready, 'error' => null]);

            // Hier wird die Tatsache wahr, also geht hier die Nachricht raus.
            // Ein Bau über 14 GB dauert Minuten — niemand sitzt so lange vor
            // der Seite und lädt neu.
            app(NotifyZipWaiters::class)($zipBuild);
        } catch (Throwable $e) {
            // ponytail: volumes already uploaded before the failing one stay
            // on the bucket, orphaned under this build's own prefix — no
            // reaper covers them (ReapIncompleteMultipartUploads only scans
            // quarantine/). Add one if abandoned ZIP builds prove to
            // accumulate; a Failed row is never served, so this is a storage
            // leak, not a correctness problem.
            $zipBuild->update([
                'status' => ZipBuildStatus::Failed,
                'error' => Str::limit($e->getMessage(), 2000, ''),
            ]);

            throw $e;
        }
    }

    /**
     * @param  list<ZipBuildMember>  $members
     */
    private function buildVolume(ZipBuild $zipBuild, FilesystemAdapter $disk, array $members, int $partNumber): void
    {
        $key = $this->partKey($zipBuild, $partNumber);
        $bytes = $this->stream($key, fn (ZipStream $zip) => $this->addMembers($zip, $disk, $members));

        ZipBuildPart::create([
            'zip_build_id' => $zipBuild->id,
            'part_number' => $partNumber,
            'key' => $key,
            'byte_size' => $bytes,
        ]);
    }

    /**
     * One archive volume, streamed into a multipart upload at $key. Also the
     * way StudioExport writes its archives; ZIP64 stays off unless the
     * caller's archive can outgrow a volume.
     *
     * @param  callable(ZipStream): void  $fill
     * @return int the object's size in bytes
     */
    public function stream(string $key, callable $fill, bool $zip64 = false): int
    {
        $uploader = new MultipartZipUploader($this->gateway, $key);

        try {
            $output = CallbackStreamWrapper::open(fn (string $chunk) => $uploader->write($chunk));

            $zip = new ZipStream(
                outputStream: $output,
                sendHttpHeaders: false,
                enableZip64: $zip64,
                defaultCompressionMethod: CompressionMethod::STORE,
            );

            $fill($zip);

            $zip->finish();
            fclose($output);
            $uploader->complete();
        } catch (Throwable $e) {
            $uploader->abort();

            throw $e;
        }

        return $uploader->totalBytes();
    }

    /**
     * @param  list<ZipBuildMember>  $members
     */
    public function addMembers(ZipStream $zip, FilesystemAdapter $disk, array $members): void
    {
        foreach ($members as $member) {
            $stream = $disk->readStream($member->key);
            if ($stream === null || $stream === false) {
                throw new RuntimeException("Unable to open {$member->key} for reading.");
            }

            try {
                $zip->addFileFromStream(fileName: $member->name, stream: $stream, exactSize: $member->size);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }
    }

    /**
     * @return list<ZipBuildMember>
     */
    /**
     * G-04 (#232): resolved by storage key, not by public_id.
     *
     * A GalleryVersion's `assets` is a list of objects carrying `key`,
     * `width` and `height` — that is what every reader has always expected
     * (the portal grid needs the dimensions to lay out without reflow) and
     * what publish() copies out of Gallery::assets. This method alone read
     * the same column as a list of public_id strings, so the first real
     * published version it met threw "Nested arrays may not be passed to
     * whereIn method" and no archive could ever be built.
     *
     * The object form wins because it is the only one that carries the
     * measurements; a version written in the old string form still resolves,
     * since a bare string is its own key.
     */
    private function resolveMembers(GalleryVersion $version, ZipBuildSize $size, ?GalleryList $list): array
    {
        $keys = collect($version->assets)
            ->map(fn (array|string $asset) => is_array($asset) ? ($asset['key'] ?? null) : $asset)
            ->filter()
            ->values();

        // G-03 (#231): a build may cover one of the client's own selections
        // instead of the whole version. Intersected rather than queried from
        // the list, so the version's own order survives — a couple's album
        // downloads in the order the studio delivered it.
        if ($list !== null) {
            $onList = $list->items->pluck('asset_key')->all();
            $keys = $keys->filter(fn (string $key) => in_array($key, $onList, true))->values();
        }

        $assetsByKey = Asset::query()
            ->where('gallery_id', $version->gallery_id)
            ->whereIn('key', $keys)
            ->where('status', AssetStatus::Ready)
            ->get()
            ->keyBy('key');

        $assets = $keys
            ->map(fn (string $key) => $assetsByKey->get($key))
            ->filter()
            ->values();

        return match ($size) {
            ZipBuildSize::Original => $assets
                ->map(fn (Asset $asset) => new ZipBuildMember($asset->key, $asset->filename, $asset->byte_size))
                ->all(),
            ZipBuildSize::Web => $assets
                ->map(fn (Asset $asset) => $this->webMember($asset))
                ->filter()
                ->values()
                ->all(),
        };
    }

    /** The widest WebP derivative G-03 generated for $asset, or null if it has none (e.g. never processed past Failed). */
    private function webMember(Asset $asset): ?ZipBuildMember
    {
        $derivative = $asset->derivatives()->where('format', 'webp')->orderByDesc('width')->first();
        if ($derivative === null) {
            return null;
        }

        $name = pathinfo($asset->filename, PATHINFO_FILENAME).'.webp';

        return new ZipBuildMember($derivative->key, $name, $derivative->bytes);
    }

    /** Partitioned by project and gallery, per G-01a's reserved layout — everything a gallery owns sits under one prefix. */
    private function partKey(ZipBuild $zipBuild, int $partNumber): string
    {
        $gallery = $zipBuild->galleryVersion->gallery;

        return "galleries/{$zipBuild->tenant_id}/{$gallery->public_id}/zips/{$zipBuild->public_id}/part-{$partNumber}.zip";
    }
}
