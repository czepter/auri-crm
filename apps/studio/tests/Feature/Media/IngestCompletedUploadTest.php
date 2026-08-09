<?php

use App\Gallery\Models\Gallery;
use App\Media\Actions\IngestCompletedUpload;
use App\Media\Enums\AssetStatus;
use App\Media\Enums\MultipartUploadStatus;
use App\Media\Jobs\ProcessIngestedAsset;
use App\Media\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// syntheticJpeg() and completedUpload() live in tests/Pest.php.

beforeEach(fn () => Storage::fake('s3'));

it('ingests a completed upload into a Ready asset with checksum, dimensions, phash and blurhash', function () {
    $gallery = Gallery::factory()->create();
    $bytes = syntheticJpeg(300, 200);
    $upload = completedUpload($gallery, $bytes);

    app(IngestCompletedUpload::class)($upload);

    $asset = Asset::sole();
    expect($asset->status)->toBe(AssetStatus::Ready)
        ->and($asset->gallery_id)->toBe($gallery->id)
        ->and($asset->checksum)->toHaveLength(64)
        ->and($asset->checksum)->toBe(hash('sha256', $bytes))
        ->and($asset->width)->toBe(300)
        ->and($asset->height)->toBe(200)
        ->and($asset->phash)->toMatch('/^[0-9A-F]{16}$/')
        ->and($asset->blurhash)->not->toBeNull()
        ->and($asset->key)->not->toBeNull()
        ->and($asset->error)->toBeNull();

    Storage::disk('s3')->assertExists($asset->key);
    Storage::disk('s3')->assertMissing($upload->key);

    expect($upload->fresh()->processed_at)->not->toBeNull()
        ->and($upload->fresh()->asset_id)->toBe($asset->id);
});

it('promotes the original into the gallery-scoped prefix, never leaving it under quarantine/', function () {
    $gallery = Gallery::factory()->create();
    $upload = completedUpload($gallery, syntheticJpeg());

    app(IngestCompletedUpload::class)($upload);

    $asset = Asset::sole();
    expect($asset->key)->toContain("galleries/{$upload->tenant_id}/{$gallery->public_id}/originals/{$asset->public_id}/")
        ->not->toContain('quarantine/');
});

it('clamps the derivative ladder to the source width and never upscales', function () {
    // 300px source: every ladder rung (2560/1600/800/400) exceeds it, so
    // exactly one width comes out — the source's own — in both formats.
    $gallery = Gallery::factory()->create();
    $upload = completedUpload($gallery, syntheticJpeg(300, 200));

    app(IngestCompletedUpload::class)($upload);

    $derivatives = Asset::sole()->derivatives;

    expect($derivatives)->toHaveCount(2)
        ->and($derivatives->pluck('width')->unique()->all())->toBe([300])
        ->and($derivatives->pluck('format')->sort()->values()->all())->toBe(['avif', 'webp']);

    foreach ($derivatives as $derivative) {
        Storage::disk('s3')->assertExists($derivative->key);
        expect($derivative->bytes)->toBeGreaterThan(0);
    }
});

it('walks every ladder rung at or below the source width, chained from a single decode', function () {
    $gallery = Gallery::factory()->create();
    $upload = completedUpload($gallery, syntheticJpeg(900, 600));

    app(IngestCompletedUpload::class)($upload);

    $derivatives = Asset::sole()->derivatives;

    expect($derivatives->pluck('width')->unique()->sort()->values()->all())->toBe([400, 800])
        ->and($derivatives)->toHaveCount(4);
});

it('is idempotent on checksum: the same bytes uploaded twice converge on one asset', function () {
    $gallery = Gallery::factory()->create();
    $bytes = syntheticJpeg();

    $first = completedUpload($gallery, $bytes, 'IMG_0001.jpg');
    app(IngestCompletedUpload::class)($first);

    $second = completedUpload($gallery, $bytes, 'IMG_0001_copy.jpg');
    app(IngestCompletedUpload::class)($second);

    expect(Asset::count())->toBe(1);

    $asset = Asset::sole();
    expect($second->fresh()->asset_id)->toBe($asset->id)
        ->and($second->fresh()->processed_at)->not->toBeNull();

    // The duplicate's own quarantine copy is cleaned up rather than kept
    // alongside the asset it never became.
    Storage::disk('s3')->assertMissing($second->key);
});

it('does nothing on a second invocation for the same upload — no duplicate asset, no re-run', function () {
    $gallery = Gallery::factory()->create();
    $upload = completedUpload($gallery, syntheticJpeg());

    app(IngestCompletedUpload::class)($upload);
    $asset = Asset::sole();

    app(IngestCompletedUpload::class)($upload->fresh());

    expect(Asset::count())->toBe(1)
        ->and(Asset::sole()->id)->toBe($asset->id)
        ->and(Asset::sole()->derivatives)->toHaveCount(2);
});

it('does not reprocess an upload whose session was aborted after the job was queued', function () {
    $gallery = Gallery::factory()->create();
    $upload = completedUpload($gallery, syntheticJpeg());
    $upload->forceFill(['status' => MultipartUploadStatus::Aborted])->save();

    app(IngestCompletedUpload::class)($upload);

    expect(Asset::count())->toBe(0)
        ->and($upload->fresh()->processed_at)->toBeNull();
});

it('marks a corrupt upload Failed with an error, rather than throwing, and leaves it in quarantine', function () {
    $gallery = Gallery::factory()->create();
    $upload = completedUpload($gallery, "this is not an image\n", 'broken.jpg');

    app(IngestCompletedUpload::class)($upload);

    $asset = Asset::sole();
    expect($asset->status)->toBe(AssetStatus::Failed)
        ->and($asset->error)->not->toBeNull()
        ->and($asset->key)->toBeNull()
        ->and($asset->derivatives)->toHaveCount(0)
        ->and($asset->checksum)->toBe(hash('sha256', "this is not an image\n"));

    expect($upload->fresh()->processed_at)->not->toBeNull()
        ->and($upload->fresh()->asset_id)->toBe($asset->id);

    // Never promoted, never silently deleted — left for an operator to find
    // via G-04's "needs attention" tray.
    Storage::disk('s3')->assertExists($upload->key);
});

it('reprocessing the same corrupt bytes converges on the one Failed asset row', function () {
    $gallery = Gallery::factory()->create();
    $bytes = "not an image\n";

    $first = completedUpload($gallery, $bytes, 'broken1.jpg');
    app(IngestCompletedUpload::class)($first);

    $second = completedUpload($gallery, $bytes, 'broken2.jpg');
    app(IngestCompletedUpload::class)($second);

    expect(Asset::count())->toBe(1)
        ->and(Asset::sole()->status)->toBe(AssetStatus::Failed);
});

it('runs end-to-end through the media.ingest job, not just through the action directly', function () {
    $gallery = Gallery::factory()->create();
    $upload = completedUpload($gallery, syntheticJpeg());

    // QUEUE_CONNECTION=sync in tests (phpunit.xml), so this executes inline
    // — proving the job actually resolves and delegates to the action,
    // rather than only ever being exercised by calling the action directly.
    ProcessIngestedAsset::dispatch($upload)->onQueue('media.ingest');

    expect(Asset::where('gallery_id', $gallery->id)->count())->toBe(1);
});
