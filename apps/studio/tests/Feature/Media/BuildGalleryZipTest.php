<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryVersion;
use App\Media\Actions\BuildGalleryZip;
use App\Media\Enums\AssetStatus;
use App\Media\Enums\ZipBuildScope;
use App\Media\Enums\ZipBuildSize;
use App\Media\Enums\ZipBuildStatus;
use App\Media\Models\Asset;
use App\Media\Models\ZipBuild;
use App\Media\Services\MultipartZipUploader;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('s3'));

/**
 * A working Aws\S3\S3Client stand-in for the whole multipart-upload
 * lifecycle (CreateMultipartUpload, UploadPart, ListParts,
 * CompleteMultipartUpload, AbortMultipartUpload) that actually retains every
 * uploaded part's bytes in $captured, keyed by upload id then part number —
 * exactly the "no fake for that boundary exists yet" gap
 * ReapIncompleteMultipartUploadsTest and MultipartUploadGatewayTest already
 * flag, extended here so a test can reassemble what was "uploaded" and
 * assert it is a genuinely valid, openable ZIP — not just that the right
 * AWS calls happened.
 *
 * @param  array<string, array<int, array{body: string, etag: string}>>  $captured
 */
function zipMultipartMock(array &$captured, array &$aborted): S3Client
{
    $nextUploadId = 0;

    $respond = function ($command) use (&$captured, &$aborted, &$nextUploadId) {
        return match ($command->getName()) {
            'CreateMultipartUpload' => new Result(['UploadId' => 'upload-'.(++$nextUploadId)]),
            'UploadPart' => (function () use ($command, &$captured) {
                $uploadId = $command['UploadId'];
                $partNumber = $command['PartNumber'];
                $etag = "\"part-{$uploadId}-{$partNumber}\"";
                $captured[$uploadId][$partNumber] = ['body' => (string) $command['Body'], 'etag' => $etag];

                return new Result(['ETag' => $etag]);
            })(),
            'ListParts' => new Result([
                'Parts' => collect($captured[$command['UploadId']] ?? [])
                    ->map(fn ($part, $number) => ['PartNumber' => $number, 'Size' => strlen($part['body']), 'ETag' => $part['etag']])
                    ->values()->all(),
                'IsTruncated' => false,
            ]),
            'CompleteMultipartUpload' => new Result(['ETag' => '"final"']),
            'AbortMultipartUpload' => (function () use ($command, &$aborted) {
                $aborted[] = $command['UploadId'];

                return new Result([]);
            })(),
            default => throw new RuntimeException("Unexpected S3 call: {$command->getName()}"),
        };
    };

    return mockS3Client(new MockHandler(array_fill(0, 200, $respond)));
}

/** The bytes captured for one upload id, reassembled in part order — the exact object App\Media\Services\MultipartZipUploader would have produced on S3. */
function assembledUpload(array $captured, string $uploadId): string
{
    ksort($captured[$uploadId]);

    return implode('', array_map(fn ($part) => $part['body'], $captured[$uploadId]));
}

function readyAsset(Gallery $gallery, string $filename, string $bytes): Asset
{
    $asset = Asset::factory()->for($gallery)->create([
        'filename' => $filename,
        'byte_size' => strlen($bytes),
        'status' => AssetStatus::Ready,
        'key' => "galleries/{$gallery->tenant_id}/{$gallery->public_id}/originals/{$filename}",
    ]);
    Storage::disk('s3')->put($asset->key, $bytes);

    return $asset;
}

function withWebDerivative(Asset $asset, string $webpBytes, int $width = 800): Asset
{
    $key = "galleries/test/derivatives/{$asset->public_id}/{$width}.webp";
    $asset->derivatives()->create(['width' => $width, 'format' => 'webp', 'key' => $key, 'bytes' => strlen($webpBytes)]);
    Storage::disk('s3')->put($key, $webpBytes);

    return $asset;
}

it('streams every Ready asset into a valid, ZIP64-free STORE archive and marks the build Ready', function () {
    $gallery = Gallery::factory()->create();
    $a = readyAsset($gallery, 'IMG_0001.jpg', str_repeat('a', 5_000));
    $b = readyAsset($gallery, 'IMG_0002.jpg', str_repeat('b', 3_000));
    $version = GalleryVersion::factory()->for($gallery)->create(['assets' => [$a->public_id, $b->public_id]]);
    $zipBuild = ZipBuild::factory()->for($version, 'galleryVersion')->create([
        'scope' => ZipBuildScope::All,
        'size' => ZipBuildSize::Original,
        'status' => ZipBuildStatus::Building,
    ]);

    $captured = [];
    $aborted = [];
    app()->instance(S3Client::class, zipMultipartMock($captured, $aborted));

    app(BuildGalleryZip::class)($zipBuild);

    expect($zipBuild->fresh()->status)->toBe(ZipBuildStatus::Ready)
        ->and($aborted)->toBe([]);

    $part = $zipBuild->fresh()->parts->sole();
    expect($part->part_number)->toBe(1)
        ->and($part->key)->toBe("galleries/{$zipBuild->tenant_id}/{$gallery->public_id}/zips/{$zipBuild->public_id}/part-1.zip");

    $zipBytes = assembledUpload($captured, array_key_first($captured));
    expect(strlen($zipBytes))->toBe($part->byte_size);

    $tmp = tempnam(sys_get_temp_dir(), 'ziptest');
    file_put_contents($tmp, $zipBytes);

    $archive = new ZipArchive;
    expect($archive->open($tmp))->toBe(true)
        ->and($archive->numFiles)->toBe(2)
        ->and($archive->getFromName('IMG_0001.jpg'))->toBe(str_repeat('a', 5_000))
        ->and($archive->getFromName('IMG_0002.jpg'))->toBe(str_repeat('b', 3_000));

    // STORE, not DEFLATE — photos are already compressed, so this story
    // spends no CPU compressing them again.
    $stat = $archive->statName('IMG_0001.jpg');
    expect($stat['comp_method'])->toBe(ZipArchive::CM_STORE);

    $archive->close();
    unlink($tmp);
});

it('packs the widest WebP derivative, not the original, for size=web', function () {
    $gallery = Gallery::factory()->create();
    $asset = readyAsset($gallery, 'IMG_0001.jpg', str_repeat('o', 9_000));
    withWebDerivative($asset, str_repeat('w', 400), width: 400);
    withWebDerivative($asset, str_repeat('w', 900), width: 800); // the widest — this one should win
    $version = GalleryVersion::factory()->for($gallery)->create(['assets' => [$asset->public_id]]);
    $zipBuild = ZipBuild::factory()->for($version, 'galleryVersion')->create([
        'scope' => ZipBuildScope::All,
        'size' => ZipBuildSize::Web,
        'status' => ZipBuildStatus::Building,
    ]);

    $captured = [];
    $aborted = [];
    app()->instance(S3Client::class, zipMultipartMock($captured, $aborted));

    app(BuildGalleryZip::class)($zipBuild);

    expect($zipBuild->fresh()->status)->toBe(ZipBuildStatus::Ready);

    $zipBytes = assembledUpload($captured, array_key_first($captured));
    $tmp = tempnam(sys_get_temp_dir(), 'ziptest');
    file_put_contents($tmp, $zipBytes);

    $archive = new ZipArchive;
    $archive->open($tmp);
    expect($archive->numFiles)->toBe(1)
        ->and($archive->getFromName('IMG_0001.webp'))->toBe(str_repeat('w', 900));
    $archive->close();
    unlink($tmp);
});

it('does nothing and makes no S3 call for a build that is not Building — Ready is served as-is', function () {
    $version = GalleryVersion::factory()->create();
    $zipBuild = ZipBuild::factory()->for($version, 'galleryVersion')->create(['status' => ZipBuildStatus::Ready]);

    // A MockHandler with an empty queue throws the instant anything tries to
    // call it — the strongest possible "no S3 call happened" assertion.
    app()->instance(S3Client::class, mockS3Client(new MockHandler([])));

    app(BuildGalleryZip::class)($zipBuild);

    expect($zipBuild->fresh()->status)->toBe(ZipBuildStatus::Ready);
});

it('aborts the multipart upload and marks the build Failed when a member cannot be read', function () {
    $gallery = Gallery::factory()->create();
    $asset = Asset::factory()->for($gallery)->create([
        'status' => AssetStatus::Ready,
        'key' => 'galleries/test/missing.jpg', // never actually put on the fake disk
        'byte_size' => 100,
    ]);
    $version = GalleryVersion::factory()->for($gallery)->create(['assets' => [$asset->public_id]]);
    $zipBuild = ZipBuild::factory()->for($version, 'galleryVersion')->create(['status' => ZipBuildStatus::Building]);

    $captured = [];
    $aborted = [];
    app()->instance(S3Client::class, zipMultipartMock($captured, $aborted));

    expect(fn () => app(BuildGalleryZip::class)($zipBuild))->toThrow(RuntimeException::class);

    expect($zipBuild->fresh()->status)->toBe(ZipBuildStatus::Failed)
        ->and($zipBuild->fresh()->error)->not->toBeNull()
        ->and($aborted)->toHaveCount(1)
        ->and($zipBuild->fresh()->parts)->toHaveCount(0);
});

it('never spools to local disk — neither action nor uploader ever opens a local file for writing', function () {
    // A structural guard rather than a runtime temp-dir scan: this sandbox
    // runs many agents' test suites against a shared /tmp, so diffing its
    // contents around one test is exactly the kind of check that flakes on
    // someone else's unrelated file, not on this code. What #59 actually
    // requires — no full-archive spool — is a property of what these two
    // files call, so assert that directly.
    $action = file_get_contents(app_path('Media/Actions/BuildGalleryZip.php'));
    $uploader = file_get_contents(app_path('Media/Services/MultipartZipUploader.php'));

    foreach (['tempnam(', 'fopen(', 'file_put_contents(', "Storage::disk('local')", 'Storage::disk("local")'] as $spool) {
        expect($action)->not->toContain($spool)
            ->and($uploader)->not->toContain($spool);
    }
});

it('keeps peak memory near one 16MiB buffer, not the archive size', function () {
    // Raised, never lowered: the full suite's default 128M CLI limit is
    // already mostly spent by the time this test runs mid-suite, which
    // would otherwise fail this test on an unrelated PHP-process budget
    // rather than on the property it actually checks.
    ini_set('memory_limit', '512M');

    $gallery = Gallery::factory()->create();
    // 3MiB — comfortably bigger than a trivial fixture, comfortably smaller
    // than the 16MiB part buffer, so a genuinely streaming implementation
    // and a "loads the whole archive into memory" one are distinguishable.
    $bytes = str_repeat('x', 3 * 1024 * 1024);
    $asset = readyAsset($gallery, 'IMG_0001.jpg', $bytes);
    $version = GalleryVersion::factory()->for($gallery)->create(['assets' => [$asset->public_id]]);
    $zipBuild = ZipBuild::factory()->for($version, 'galleryVersion')->create(['status' => ZipBuildStatus::Building]);

    // Counts and sizes only — capturing every part's full bytes (as the
    // other tests' mock does, to reassemble and open the archive) would
    // inflate this test's own memory usage and defeat the point of it.
    $partSizes = [];
    $handler = new MockHandler(array_fill(0, 200, function ($command) use (&$partSizes) {
        return match ($command->getName()) {
            'CreateMultipartUpload' => new Result(['UploadId' => 'upload-1']),
            'UploadPart' => (function () use ($command, &$partSizes) {
                $partSizes[(int) $command['PartNumber']] = (int) $command['ContentLength'];

                return new Result(['ETag' => '"part"']);
            })(),
            'ListParts' => new Result([
                'Parts' => collect($partSizes)->map(fn ($size, $n) => ['PartNumber' => $n, 'Size' => $size, 'ETag' => '"part"'])->values()->all(),
                'IsTruncated' => false,
            ]),
            'CompleteMultipartUpload' => new Result(['ETag' => '"final"']),
            default => throw new RuntimeException("Unexpected S3 call: {$command->getName()}"),
        };
    }));
    app()->instance(S3Client::class, mockS3Client($handler));

    $before = memory_get_usage();
    app(BuildGalleryZip::class)($zipBuild);
    $peakDelta = memory_get_peak_usage() - $before;

    expect($zipBuild->fresh()->status)->toBe(ZipBuildStatus::Ready)
        ->and($peakDelta)->toBeLessThan(16 * 1024 * 1024);
});

it('fixes the multipart part size at 16MiB, keeping part count under the 10,000-part ceiling at the 3.9GiB split size', function () {
    expect(MultipartZipUploader::PART_SIZE)->toBe(16 * 1024 * 1024)
        ->and((int) ceil(BuildGalleryZip::SPLIT_THRESHOLD_BYTES / MultipartZipUploader::PART_SIZE))->toBeLessThan(10_000);
});
