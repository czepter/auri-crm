<?php

use App\Enums\StaffRole;
use App\Gallery\Jobs\ProcessIngestedAsset;
use App\Gallery\Models\Gallery;
use App\Media\Enums\AssetStatus;
use App\Media\Models\Asset;
use App\Media\Models\MultipartUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// staffMember() and integerIdPaths() live in tests/Pest.php.

it('lists this gallery\'s Failed assets on its record, and none of another gallery\'s', function () {
    $gallery = Gallery::factory()->create();
    $otherGallery = Gallery::factory()->create();

    $failed = Asset::factory()->for($gallery)->create([
        'status' => AssetStatus::Failed,
        'error' => 'Prüfsumme weicht ab — die übertragene Datei ist unvollständig.',
        'filename' => 'IMG_5107.jpg',
    ]);
    Asset::factory()->for($gallery)->create(['status' => AssetStatus::Ready]);
    Asset::factory()->for($otherGallery)->create(['status' => AssetStatus::Failed]);

    $response = $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->get("/galerien/{$gallery->public_id}")
        ->assertOk();

    $props = $response->viewData('page')['props'];

    expect($props['failedAssets'])->toHaveCount(1)
        ->and($props['failedAssets'][0]['publicId'])->toBe($failed->public_id)
        ->and($props['failedAssets'][0]['filename'])->toBe('IMG_5107.jpg')
        ->and($props['failedAssets'][0]['error'])->toBe($failed->error)
        ->and(integerIdPaths($props))->toBe([]);
});

it('retries a Failed asset and sends the browser back to the record', function () {
    Queue::fake();

    $gallery = Gallery::factory()->create();
    $failed = Asset::factory()->for($gallery)->create(['status' => AssetStatus::Failed]);
    $upload = MultipartUpload::factory()->for($gallery)->create(['asset_id' => $failed->id, 'processed_at' => now()]);

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->from("/galerien/{$gallery->public_id}")
        ->post("/medien/{$failed->public_id}/wiederholen")
        ->assertRedirect("/galerien/{$gallery->public_id}");

    expect(Asset::query()->whereKey($failed->id)->exists())->toBeFalse();
    expect($upload->fresh()->processed_at)->toBeNull();

    Queue::assertPushedOn('media.ingest', ProcessIngestedAsset::class, fn ($job) => $job->multipartUpload->is($upload));
});

it('refuses to retry an asset that is not Failed', function () {
    $gallery = Gallery::factory()->create();
    $ready = Asset::factory()->for($gallery)->create(['status' => AssetStatus::Ready]);

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->post("/medien/{$ready->public_id}/wiederholen")
        ->assertStatus(409);

    expect(Asset::query()->whereKey($ready->id)->exists())->toBeTrue();
});

it('refuses a role without ManageShoots, and lets one with it through', function (StaffRole $role, int $status) {
    $gallery = Gallery::factory()->create();
    $failed = Asset::factory()->for($gallery)->create(['status' => AssetStatus::Failed]);
    MultipartUpload::factory()->for($gallery)->create(['asset_id' => $failed->id, 'processed_at' => now()]);

    $this->actingAs(staffMember($role), 'staff')
        ->post("/medien/{$failed->public_id}/wiederholen")
        ->assertStatus($status);
})->with([
    [StaffRole::Admin, 302],
    [StaffRole::Manager, 302],
    [StaffRole::Contributor, 403],
    [StaffRole::Reader, 403],
]);
