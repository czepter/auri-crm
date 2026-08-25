<?php

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
    $this->highlights = GallerySet::where('gallery_id', $this->gallery->id)->first();
    $this->feier = GallerySet::factory()->for($this->gallery)->create(['name' => 'Feier', 'position' => 1]);
});

it('moves a selection into another set', function () {
    $one = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->highlights->id]);
    $two = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->highlights->id]);

    $this->post("/galerien/{$this->gallery->public_id}/bilder/verschieben", [
        'asset_ids' => [$one->public_id, $two->public_id],
        'gallery_set_id' => $this->feier->public_id,
    ])->assertRedirect();

    expect($one->fresh()->gallery_set_id)->toBe($this->feier->id)
        ->and($two->fresh()->gallery_set_id)->toBe($this->feier->id);
});

it('leaves another gallery\'s picture out of a move', function () {
    $mine = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->highlights->id]);
    $foreign = Asset::factory()->create();

    $this->post("/galerien/{$this->gallery->public_id}/bilder/verschieben", [
        'asset_ids' => [$mine->public_id, $foreign->public_id],
        'gallery_set_id' => $this->feier->public_id,
    ])->assertRedirect();

    expect($mine->fresh()->gallery_set_id)->toBe($this->feier->id)
        ->and($foreign->fresh()->gallery_set_id)->toBe($foreign->gallery_set_id);
});

it('takes a picture out of the draft without touching the row', function () {
    $asset = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->highlights->id]);

    $this->delete("/galerien/{$this->gallery->public_id}/bilder", ['asset_ids' => [$asset->public_id]])
        ->assertRedirect();

    expect($asset->fresh()->removed_from_draft_at)->not->toBeNull()
        ->and(Asset::whereKey($asset->id)->exists())->toBeTrue();
});

it('leaves another gallery\'s picture out of a remove', function () {
    $foreign = Asset::factory()->create();

    $this->delete("/galerien/{$this->gallery->public_id}/bilder", ['asset_ids' => [$foreign->public_id]])
        ->assertRedirect();

    expect($foreign->fresh()->removed_from_draft_at)->toBeNull();
});

it('clears the cover when the cover picture leaves the draft', function () {
    $asset = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->highlights->id]);
    $this->gallery->update(['cover_asset_id' => $asset->id, 'cover_focal_x' => 58, 'cover_focal_y' => 34]);

    $this->delete("/galerien/{$this->gallery->public_id}/bilder", ['asset_ids' => [$asset->public_id]])
        ->assertRedirect();

    expect($this->gallery->fresh()->cover_asset_id)->toBeNull()
        ->and($this->gallery->fresh()->cover_focal_x)->toBeNull()
        ->and($this->gallery->fresh()->cover_focal_y)->toBeNull();
});

it('sets the cover and its focal point', function () {
    $asset = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->highlights->id]);

    $this->patch("/galerien/{$this->gallery->public_id}/titelbild", [
        'asset_id' => $asset->public_id,
        'focal_x' => 58,
        'focal_y' => 34,
    ])->assertRedirect();

    expect($this->gallery->fresh()->cover_asset_id)->toBe($asset->id)
        ->and($this->gallery->fresh()->cover_focal_x)->toBe(58);
});

it('drops the focal point when the cover changes', function () {
    $first = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->highlights->id]);
    $second = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->highlights->id]);

    $this->patch("/galerien/{$this->gallery->public_id}/titelbild", [
        'asset_id' => $first->public_id, 'focal_x' => 58, 'focal_y' => 34,
    ]);
    $this->patch("/galerien/{$this->gallery->public_id}/titelbild", ['asset_id' => $second->public_id]);

    expect($this->gallery->fresh()->cover_focal_x)->toBeNull()
        ->and($this->gallery->fresh()->cover_focal_y)->toBeNull();
});

it('keeps the focal point when the same cover is saved again without one', function () {
    $asset = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->highlights->id]);

    $this->patch("/galerien/{$this->gallery->public_id}/titelbild", [
        'asset_id' => $asset->public_id, 'focal_x' => 58, 'focal_y' => 34,
    ]);
    $this->patch("/galerien/{$this->gallery->public_id}/titelbild", ['asset_id' => $asset->public_id]);

    expect($this->gallery->fresh()->cover_focal_x)->toBe(58)
        ->and($this->gallery->fresh()->cover_focal_y)->toBe(34);
});

it('redirects to a signed original url', function () {
    $asset = Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->highlights->id,
        'key' => 'galleries/test/original.jpg',
    ]);

    $response = $this->get("/galerien/{$this->gallery->public_id}/bilder/{$asset->public_id}/original");

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('galleries/test/original.jpg');
});

it('refuses another gallery\'s asset on the original route', function () {
    $foreign = Asset::factory()->create();

    $this->get("/galerien/{$this->gallery->public_id}/bilder/{$foreign->public_id}/original")
        ->assertNotFound();
});

it('serves a draft asset\'s preview bytes without any published version', function () {
    Storage::fake('s3');
    $asset = Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->highlights->id,
        'key' => 'galleries/test/a.jpg',
    ]);
    $image = imagecreatetruecolor(200, 100);
    ob_start();
    imagejpeg($image);
    Storage::disk('s3')->put($asset->key, ob_get_clean());

    $this->get("/galerien/{$this->gallery->public_id}/bilder/{$asset->public_id}/vorschau?w=400&fmt=jpeg")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

it('refuses another gallery\'s asset on the preview route', function () {
    // The bytes genuinely exist and are servable — Storage::fake('s3') plus
    // a real file at $foreign->key — so the only thing that can produce a
    // 404 here is the ownership guard itself, not ImageVariant::deliver()'s
    // own "no such key" check.
    Storage::fake('s3');
    $foreign = Asset::factory()->create(['key' => 'galleries/other/a.jpg']);
    $image = imagecreatetruecolor(200, 100);
    ob_start();
    imagejpeg($image);
    Storage::disk('s3')->put($foreign->key, ob_get_clean());

    $this->get("/galerien/{$this->gallery->public_id}/bilder/{$foreign->public_id}/vorschau?w=400&fmt=jpeg")
        ->assertNotFound();
});

it('refuses a Failed asset on the preview route — it has no key to serve', function () {
    $failed = Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->highlights->id,
        'status' => AssetStatus::Failed, 'key' => null, 'filename' => 'c.jpg', 'error' => 'corrupt file',
    ]);

    $this->get("/galerien/{$this->gallery->public_id}/bilder/{$failed->public_id}/vorschau?w=400&fmt=jpeg")
        ->assertNotFound();
});
