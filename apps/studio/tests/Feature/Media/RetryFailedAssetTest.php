<?php

use App\Gallery\Models\Gallery;
use App\Media\Actions\IngestCompletedUpload;
use App\Media\Actions\RetryFailedAsset;
use App\Media\Enums\AssetStatus;
use App\Media\Jobs\ProcessIngestedAsset;
use App\Media\Models\Asset;
use App\Media\Models\MultipartUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// syntheticJpeg() and completedUpload() live in tests/Pest.php.

beforeEach(fn () => Storage::fake('s3'));

it('deletes the Failed asset, resets the upload, and re-dispatches ingest onto media.ingest', function () {
    Queue::fake();

    $gallery = Gallery::factory()->create();
    $upload = completedUpload($gallery, "not an image\n", 'broken.jpg');
    app(IngestCompletedUpload::class)($upload);

    $failed = Asset::sole();
    expect($failed->status)->toBe(AssetStatus::Failed);
    $upload->refresh();
    expect($upload->processed_at)->not->toBeNull()
        ->and($upload->asset_id)->toBe($failed->id);

    app(RetryFailedAsset::class)($failed);

    expect(Asset::query()->whereKey($failed->id)->exists())->toBeFalse();

    $upload->refresh();
    expect($upload->processed_at)->toBeNull()
        ->and($upload->asset_id)->toBeNull();

    Queue::assertPushedOn('media.ingest', ProcessIngestedAsset::class, fn ($job) => $job->multipartUpload->is($upload));
});

it('actually re-runs the pipeline rather than no-oping on the checksum it already knows', function () {
    // The whole point of deleting the Failed row first: IngestCompletedUpload's
    // own dedup lookup (`Asset::where(gallery_id, checksum)->first()`) does not
    // filter by status, so if the old Failed row were left in place a retry
    // would just match it and return without ever calling process() again.
    $gallery = Gallery::factory()->create();
    $bytes = "not an image\n";
    $upload = completedUpload($gallery, $bytes, 'broken.jpg');
    app(IngestCompletedUpload::class)($upload);

    $failed = Asset::sole();
    $originalId = $failed->id;

    // QUEUE_CONNECTION=sync in tests, so this dispatch — done inside
    // RetryFailedAsset itself — runs the whole pipeline inline.
    app(RetryFailedAsset::class)($failed);

    $retried = Asset::sole();
    expect($retried->id)->not->toBe($originalId)
        ->and($retried->status)->toBe(AssetStatus::Failed)
        ->and($retried->error)->not->toBeNull()
        ->and($retried->checksum)->toBe(hash('sha256', $bytes));
});

it('succeeds on retry once the underlying bytes are fixed', function () {
    $gallery = Gallery::factory()->create();
    $upload = completedUpload($gallery, "not an image\n", 'broken.jpg');
    app(IngestCompletedUpload::class)($upload);

    $failed = Asset::sole();
    expect($failed->status)->toBe(AssetStatus::Failed);

    // Stands in for "an operator re-uploads the same file correctly" — the
    // bytes sitting at the upload's own quarantine key are swapped for a
    // real image before the retry re-reads them.
    Storage::disk('s3')->put($upload->key, syntheticJpeg(300, 200));

    app(RetryFailedAsset::class)($failed);

    $asset = Asset::sole();
    expect($asset->status)->toBe(AssetStatus::Ready)
        ->and($asset->width)->toBe(300)
        ->and($asset->error)->toBeNull();
});

it('picks up the most recent upload session when more than one converged on the same Failed asset', function () {
    $gallery = Gallery::factory()->create();
    $bytes = "not an image\n";

    $first = completedUpload($gallery, $bytes, 'broken1.jpg');
    app(IngestCompletedUpload::class)($first);

    $second = completedUpload($gallery, $bytes, 'broken2.jpg');
    app(IngestCompletedUpload::class)($second);

    $failed = Asset::sole();
    expect($second->fresh()->asset_id)->toBe($failed->id);

    Queue::fake();
    app(RetryFailedAsset::class)($failed);

    Queue::assertPushedOn('media.ingest', ProcessIngestedAsset::class, fn ($job) => $job->multipartUpload->is($second));
});

it('aborts with a 409 when no upload session ever pointed at this asset', function () {
    $asset = Asset::factory()->create(['status' => AssetStatus::Failed]);

    app(RetryFailedAsset::class)($asset);
})->throws(Symfony\Component\HttpKernel\Exception\HttpException::class);
