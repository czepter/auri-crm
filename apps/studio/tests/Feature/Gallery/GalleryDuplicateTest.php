<?php

use App\Gallery\Actions\IngestCompletedUpload;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Enums\AssetStatus;
use App\Media\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
    $this->gallery = Gallery::factory()->create();
    $this->set = GallerySet::where('gallery_id', $this->gallery->id)->first();
});

it('names the files the gallery already holds', function () {
    Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->set->id,
        'filename' => 'IMG_0001.jpg', 'byte_size' => 5_460_000,
    ]);

    $response = $this->postJson("/galerien/{$this->gallery->public_id}/uploads/pruefen", [
        'files' => [
            ['filename' => 'IMG_0001.jpg', 'size' => 5_460_000],
            ['filename' => 'IMG_0002.jpg', 'size' => 5_480_000],
        ],
    ])->assertOk();

    expect($response->json('duplicates'))->toHaveCount(1)
        ->and($response->json('duplicates.0.filename'))->toBe('IMG_0001.jpg');
});

it('looks across the whole gallery, not one set', function () {
    $other = GallerySet::factory()->for($this->gallery)->create(['name' => 'Feier', 'position' => 1]);
    Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $other->id,
        'filename' => 'IMG_0001.jpg', 'byte_size' => 5_460_000,
    ]);

    $response = $this->postJson("/galerien/{$this->gallery->public_id}/uploads/pruefen", [
        'files' => [['filename' => 'IMG_0001.jpg', 'size' => 5_460_000]],
    ])->assertOk();

    expect($response->json('duplicates'))->toHaveCount(1);
});

it('ignores what already left the draft', function () {
    Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->set->id,
        'filename' => 'IMG_0001.jpg', 'byte_size' => 5_460_000, 'removed_from_draft_at' => now(),
    ]);

    $response = $this->postJson("/galerien/{$this->gallery->public_id}/uploads/pruefen", [
        'files' => [['filename' => 'IMG_0001.jpg', 'size' => 5_460_000]],
    ])->assertOk();

    expect($response->json('duplicates'))->toBeEmpty();
});

// A failed ingest still leaves a row carrying filename and byte_size — the
// precheck must catch a re-pick of the same file just as it would a Ready
// one, or IngestCompletedUpload converges on the Failed row by checksum,
// deletes the fresh quarantine object and the tray says Bereit for a
// picture that never appears (Important 1).
it('catches a duplicate even when the earlier upload failed', function () {
    Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->set->id,
        'filename' => 'IMG_0001.jpg', 'byte_size' => 5_460_000, 'status' => AssetStatus::Failed,
    ]);

    $response = $this->postJson("/galerien/{$this->gallery->public_id}/uploads/pruefen", [
        'files' => [['filename' => 'IMG_0001.jpg', 'size' => 5_460_000]],
    ])->assertOk();

    expect($response->json('duplicates'))->toHaveCount(1);
});

// syntheticJpeg() and completedUpload() live in tests/Pest.php.
it('lets a replacement inherit set and cover role', function () {
    Storage::fake('s3');

    $old = Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->set->id,
        'filename' => 'IMG_0001.jpg', 'byte_size' => 5_460_000,
    ]);
    $this->gallery->update(['cover_asset_id' => $old->id]);

    $upload = completedUpload($this->gallery, syntheticJpeg());
    $upload->forceFill(['gallery_set_id' => $this->set->id, 'replaces_asset_id' => $old->id])->save();

    app(IngestCompletedUpload::class)($upload);

    $fresh = Asset::whereNull('removed_from_draft_at')->latest('id')->first();

    expect($fresh->gallery_set_id)->toBe($this->set->id)
        ->and($this->gallery->fresh()->cover_asset_id)->toBe($fresh->id)
        ->and($old->fresh()->removed_from_draft_at)->not->toBeNull();
});
