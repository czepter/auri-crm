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
});

it('adds a set behind the last one', function () {
    $this->post("/galerien/{$this->gallery->public_id}/sets", ['name' => 'Feier'])->assertRedirect();

    expect($this->gallery->sets()->pluck('name')->all())->toBe(['Highlights', 'Feier'])
        ->and($this->gallery->sets()->where('name', 'Feier')->first()->position)->toBe(1);
});

it('refuses a name the gallery already carries', function () {
    $this->post("/galerien/{$this->gallery->public_id}/sets", ['name' => 'Highlights'])
        ->assertSessionHasErrors('name');
});

it('renames a set', function () {
    $this->patch("/galerien/{$this->gallery->public_id}/sets/{$this->highlights->public_id}", ['name' => 'Beste'])
        ->assertRedirect();

    expect($this->highlights->fresh()->name)->toBe('Beste');
});

it('changes a set\'s sort rule', function () {
    $this->patch("/galerien/{$this->gallery->public_id}/sets/{$this->highlights->public_id}", [
        'sort_key' => 'filename',
        'sort_dir' => 'desc',
    ])->assertRedirect();

    expect($this->highlights->fresh()->sort_key)->toBe('filename')
        ->and($this->highlights->fresh()->sort_dir)->toBe('desc');
});

it('refuses a sort_key outside the known set', function () {
    $this->patch("/galerien/{$this->gallery->public_id}/sets/{$this->highlights->public_id}", [
        'sort_key' => 'random',
    ])->assertSessionHasErrors('sort_key');
});

it('refuses a sort_dir outside the known set', function () {
    $this->patch("/galerien/{$this->gallery->public_id}/sets/{$this->highlights->public_id}", [
        'sort_dir' => 'sideways',
    ])->assertSessionHasErrors('sort_dir');
});

it('refuses to rename a set into a foreign gallery', function () {
    $other = GallerySet::where('gallery_id', Gallery::factory()->create()->id)->first();

    $this->patch("/galerien/{$this->gallery->public_id}/sets/{$other->public_id}", ['name' => 'Beste'])
        ->assertNotFound();
});

it('moves the pictures of a deleted set into the named target', function () {
    $feier = GallerySet::factory()->for($this->gallery)->create(['name' => 'Feier', 'position' => 1]);
    $asset = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $feier->id]);

    $this->delete("/galerien/{$this->gallery->public_id}/sets/{$feier->public_id}", [
        'target_set_id' => $this->highlights->public_id,
    ])->assertRedirect();

    expect($asset->fresh()->gallery_set_id)->toBe($this->highlights->id)
        ->and(GallerySet::whereKey($feier->id)->exists())->toBeFalse();
});

it('takes the pictures with it when no target is named', function () {
    $feier = GallerySet::factory()->for($this->gallery)->create(['name' => 'Feier', 'position' => 1]);
    $asset = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $feier->id]);

    $this->delete("/galerien/{$this->gallery->public_id}/sets/{$feier->public_id}")->assertRedirect();

    expect(Asset::whereKey($asset->id)->exists())->toBeFalse();
});

it('drops the cover when its set is deleted without a target', function () {
    $feier = GallerySet::factory()->for($this->gallery)->create(['name' => 'Feier', 'position' => 1]);
    $asset = Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $feier->id]);
    $this->gallery->update(['cover_asset_id' => $asset->id]);

    $this->delete("/galerien/{$this->gallery->public_id}/sets/{$feier->public_id}")->assertRedirect();

    expect($this->gallery->fresh()->cover_asset_id)->toBeNull();
});

it('refuses to delete the last set', function () {
    $this->delete("/galerien/{$this->gallery->public_id}/sets/{$this->highlights->public_id}")
        ->assertSessionHasErrors();

    expect(GallerySet::whereKey($this->highlights->id)->exists())->toBeTrue();
});

it('refuses a target set from a foreign gallery', function () {
    $feier = GallerySet::factory()->for($this->gallery)->create(['name' => 'Feier', 'position' => 1]);
    $foreignTarget = GallerySet::where('gallery_id', Gallery::factory()->create()->id)->first();

    $this->delete("/galerien/{$this->gallery->public_id}/sets/{$feier->public_id}", [
        'target_set_id' => $foreignTarget->public_id,
    ])->assertNotFound();

    expect(GallerySet::whereKey($feier->id)->exists())->toBeTrue();
});

it('refuses to delete a set from a foreign gallery', function () {
    $foreignSet = GallerySet::where('gallery_id', Gallery::factory()->create()->id)->first();

    $this->delete("/galerien/{$this->gallery->public_id}/sets/{$foreignSet->public_id}")
        ->assertNotFound();

    expect(GallerySet::whereKey($foreignSet->id)->exists())->toBeTrue();
});

// Proves the route ordering: if /sets/reihenfolge were registered below
// /sets/{gallerySet}, "reihenfolge" would bind as a public_id, find no
// GallerySet and 404 instead of redirecting.
it('reorders the sets', function () {
    $feier = GallerySet::factory()->for($this->gallery)->create(['name' => 'Feier', 'position' => 1]);

    $this->patch("/galerien/{$this->gallery->public_id}/sets/reihenfolge", [
        'order' => [$feier->public_id, $this->highlights->public_id],
    ])->assertRedirect();

    expect($this->gallery->sets()->pluck('name')->all())->toBe(['Feier', 'Highlights']);
});

it('refuses a reorder list missing one of the gallery\'s sets', function () {
    GallerySet::factory()->for($this->gallery)->create(['name' => 'Feier', 'position' => 1]);

    $this->patch("/galerien/{$this->gallery->public_id}/sets/reihenfolge", [
        'order' => [$this->highlights->public_id],
    ])->assertSessionHasErrors('order');

    expect($this->highlights->fresh()->position)->toBe(0);
});

it('refuses a reorder list carrying a foreign set', function () {
    $feier = GallerySet::factory()->for($this->gallery)->create(['name' => 'Feier', 'position' => 1]);
    $foreign = GallerySet::where('gallery_id', Gallery::factory()->create()->id)->first();

    $this->patch("/galerien/{$this->gallery->public_id}/sets/reihenfolge", [
        'order' => [$foreign->public_id, $feier->public_id],
    ])->assertSessionHasErrors('order.0');

    expect($feier->fresh()->position)->toBe(1);
});
