<?php

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    expect($this->gallery->fresh()->cover_focal_x)->toBeNull();
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
