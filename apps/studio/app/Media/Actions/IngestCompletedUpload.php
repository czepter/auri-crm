<?php

namespace App\Media\Actions;

use App\Gallery\Actions\EnsureGalleryCover;
use App\Gallery\Models\Gallery;
use App\Media\Enums\AssetStatus;
use App\Media\Enums\MultipartUploadStatus;
use App\Media\Models\Asset;
use App\Media\Models\MultipartUpload;
use App\Media\Support\ExifFields;
use App\Media\Support\LibvipsDerivativePipeline;
use App\Media\Support\PerceptualHash;
use App\Support\LiveUpdate;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use kornrunner\Blurhash\Blurhash;
use Throwable;
use Visus\Cuid2\Cuid2;

/**
 * G-03: what promotes a completed quarantine object (G-02) into a gallery
 * asset — checksum, EXIF, perceptual hash, libvips derivatives, blurhash.
 * Dispatched onto the media.ingest queue (App\Media\Jobs\ProcessIngestedAsset)
 * the moment MultipartUploadController::complete() marks a session Completed.
 *
 * Idempotent on SHA-256, per #52: this can run twice for the same
 * MultipartUpload (a retried job) or once each for two different
 * MultipartUpload rows that happen to hold identical bytes (the same file
 * uploaded twice), and both converge on exactly one Asset row —
 * assets_gallery_checksum_unique (the migration) is what a genuine race
 * between two workers ultimately falls back on; the processed_at/checksum
 * checks here are what make the common, non-racing case never touch the
 * database with a duplicate in the first place.
 *
 * Never throws past this method for a source-data problem (a corrupt file,
 * a format libvips cannot decode): that is turned into a Failed Asset with
 * an `error`, per #52's "mark the asset as failed... rather than throwing
 * unhandled" — Horizon's failed-jobs list is for infrastructure problems,
 * not for a client uploading a truncated JPEG.
 */
class IngestCompletedUpload
{
    /** components_x, components_y — kornrunner/blurhash's own defaults, matching every reference blurhash implementation's "4x3" example. */
    private const BLURHASH_COMPONENTS = [4, 3];

    public function __construct(
        private readonly LibvipsDerivativePipeline $vips,
        private readonly EnsureGalleryCover $ensureCover,
    ) {}

    public function __invoke(MultipartUpload $multipartUpload): void
    {
        $upload = $multipartUpload->fresh();

        if ($upload === null || $upload->status !== MultipartUploadStatus::Completed || $upload->processed_at !== null) {
            // Gone, aborted since dispatch, or already processed by an
            // earlier attempt — re-running this for an already-concluded
            // upload is exactly the duplicate work idempotency rules out.
            return;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('s3');
        $workDir = sys_get_temp_dir().'/ingest-'.Str::random(12);
        File::ensureDirectoryExists($workDir);
        $originalPath = "{$workDir}/original";
        $checksum = null;

        try {
            $checksum = $this->downloadAndHash($disk, $upload->key, $originalPath);

            $existing = Asset::query()
                ->where('gallery_id', $upload->gallery_id)
                ->where('checksum', $checksum)
                ->first();

            if ($existing !== null) {
                // The same bytes already landed as an asset in this gallery
                // — never reprocess, never keep a redundant quarantine copy.
                $disk->delete($upload->key);
                $upload->forceFill(['processed_at' => now(), 'asset_id' => $existing->id])->save();

                return;
            }

            $asset = $this->process($upload, $disk, $originalPath, $checksum, $workDir);
            $upload->forceFill(['processed_at' => now(), 'asset_id' => $asset->id])->save();
        } catch (Throwable $e) {
            $asset = $checksum !== null
                ? Asset::firstOrNew(['gallery_id' => $upload->gallery_id, 'checksum' => $checksum])
                : new Asset(['gallery_id' => $upload->gallery_id]);

            $asset->fill([
                'gallery_set_id' => $upload->gallery_set_id,
                'status' => AssetStatus::Failed,
                'error' => Str::limit($e->getMessage(), 2000, ''),
                'filename' => $upload->filename,
                'byte_size' => $upload->file_size,
            ])->save();

            $upload->forceFill(['processed_at' => now(), 'asset_id' => $asset->id])->save();
        } finally {
            File::deleteDirectory($workDir);

            // Erst wenn der Stapel durch ist. Parallele Sitzungen werden in
            // beliebiger Reihenfolge fertig; solange noch eine offen ist,
            // waere „das erste Bild" das zufaellig schnellste.
            $stillRunning = MultipartUpload::query()
                ->where('gallery_id', $upload->gallery_id)
                ->where('status', '!=', MultipartUploadStatus::Aborted)
                ->whereNull('processed_at')
                ->exists();

            if (! $stillRunning && $upload->gallery !== null) {
                ($this->ensureCover)($upload->gallery);
            }

            LiveUpdate::publish();
        }
    }

    /** Everything past "we have the bytes and they're new" — left to throw freely, the caller's catch is what turns any of it into a Failed asset. */
    private function process(MultipartUpload $upload, FilesystemAdapter $disk, string $originalPath, string $checksum, string $workDir): Asset
    {
        ['width' => $width, 'height' => $height] = $this->vips->dimensions($originalPath);

        $exif = exif_imagetype($originalPath) === IMAGETYPE_JPEG
            ? ExifFields::fromReadData(@exif_read_data($originalPath) ?: [])
            : ['takenAt' => null, 'cameraMake' => null, 'cameraModel' => null];

        $ladder = $this->vips->ladder($originalPath, $width, $workDir);

        $phashSource = "{$workDir}/phash.png";
        $this->vips->squareThumbnail($originalPath, $phashSource, 32);
        $phash = (new PerceptualHash)->hash($phashSource);

        $blurhashSource = "{$workDir}/blur.png";
        $this->vips->aspectThumbnail($originalPath, $blurhashSource, 32);
        $blurhash = Blurhash::encode($this->pixels($blurhashSource), ...self::BLURHASH_COMPONENTS);

        $assetPublicId = (string) new Cuid2;
        $finalKey = $this->assetKey($upload, $assetPublicId, 'originals', $upload->filename);
        $disk->move($upload->key, $finalKey);

        $asset = Asset::create([
            'public_id' => $assetPublicId,
            'gallery_id' => $upload->gallery_id,
            'gallery_set_id' => $upload->gallery_set_id,
            'checksum' => $checksum,
            'status' => AssetStatus::Ready,
            'key' => $finalKey,
            'filename' => $upload->filename,
            'byte_size' => $upload->file_size,
            'width' => $width,
            'height' => $height,
            'taken_at' => $exif['takenAt'],
            'camera_make' => $exif['cameraMake'],
            'camera_model' => $exif['cameraModel'],
            'phash' => $phash,
            'blurhash' => $blurhash,
        ]);

        foreach ($ladder as $derivative) {
            $derivativeKey = $this->assetKey($upload, $assetPublicId, 'derivatives', "{$derivative['width']}.{$derivative['format']}");
            $disk->put($derivativeKey, file_get_contents($derivative['path']));

            $asset->derivatives()->create([
                'width' => $derivative['width'],
                'format' => $derivative['format'],
                'key' => $derivativeKey,
                'bytes' => $derivative['bytes'],
            ]);
        }

        if ($upload->replaces_asset_id !== null) {
            $replaced = Asset::find($upload->replaces_asset_id);

            if ($replaced !== null) {
                $asset->update(['gallery_set_id' => $replaced->gallery_set_id]);
                $replaced->update(['removed_from_draft_at' => now()]);

                Gallery::where('cover_asset_id', $replaced->id)->update(['cover_asset_id' => $asset->id]);
            }
        }

        return $asset;
    }

    /**
     * Streams $key into $localPath while hashing it — 1 MiB at a time, never
     * the whole object in a single PHP string, per #52's "streamed, not
     * loaded fully into memory for large files". Writing the bytes to local
     * disk in the same pass is what libvips then reads from: vipsthumbnail
     * has no streaming-from-S3 mode of its own, so a local copy is
     * unavoidable either way, and this is the one pass that produces it.
     */
    private function downloadAndHash(FilesystemAdapter $disk, string $key, string $localPath): string
    {
        $remote = $disk->readStream($key);
        if ($remote === null || $remote === false) {
            throw new \RuntimeException("Unable to open {$key} for reading.");
        }

        $local = fopen($localPath, 'wb');
        $context = hash_init('sha256');

        try {
            while (! feof($remote)) {
                $chunk = fread($remote, 1024 * 1024);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                hash_update($context, $chunk);
                fwrite($local, $chunk);
            }
        } finally {
            fclose($remote);
            fclose($local);
        }

        return hash_final($context);
    }

    /** @return list<list<list<int>>> */
    private function pixels(string $pngPath): array
    {
        $image = @imagecreatefrompng($pngPath);
        if ($image === false) {
            throw new \RuntimeException("Unable to decode {$pngPath} for blurhash encoding.");
        }

        try {
            $width = imagesx($image);
            $height = imagesy($image);
            $pixels = [];

            for ($y = 0; $y < $height; $y++) {
                $row = [];
                for ($x = 0; $x < $width; $x++) {
                    $rgb = imagecolorat($image, $x, $y);
                    $row[] = [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
                }
                $pixels[] = $row;
            }

            return $pixels;
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * Partitioned by project — via the tenant — and gallery, per G-01a's
     * reserved layout: everything a gallery owns sits under one prefix, so
     * deleting it is a single prefix delete (OQ-5, #80).
     */
    private function assetKey(MultipartUpload $upload, string $assetPublicId, string $kind, string $name): string
    {
        return "galleries/{$upload->tenant_id}/{$upload->gallery->public_id}/{$kind}/{$assetPublicId}/{$name}";
    }
}
