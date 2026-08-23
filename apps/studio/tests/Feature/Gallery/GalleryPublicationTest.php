<?php

use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;
use App\Models\User;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
    $this->gallery = Gallery::factory()->create();
    $this->set = GallerySet::where('gallery_id', $this->gallery->id)->first();
    Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->set->id,
        'key' => 'galleries/g/a.jpg', 'filename' => 'a.jpg',
    ]);
});

it('writes a first version', function () {
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen")->assertRedirect();

    expect($this->gallery->fresh()->currentVersion->version)->toBe(1);
});

it('reopens the standing version when nothing moved', function () {
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");
    $this->delete("/galerien/{$this->gallery->public_id}/veroeffentlichung");
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");

    expect($this->gallery->fresh()->currentVersion->version)->toBe(1)
        ->and($this->gallery->fresh()->unpublished_at)->toBeNull();
});

it('writes a second version when the draft moved', function () {
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");

    Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->set->id,
        'key' => 'galleries/g/b.jpg', 'filename' => 'b.jpg',
    ]);

    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");

    expect($this->gallery->fresh()->currentVersion->version)->toBe(2);
});

it('sees a swap of equal count as a change', function () {
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");

    $this->gallery->assetRows()->update(['removed_from_draft_at' => now()]);
    Asset::factory()->create([
        'gallery_id' => $this->gallery->id, 'gallery_set_id' => $this->set->id,
        'key' => 'galleries/g/c.jpg', 'filename' => 'c.jpg',
    ]);

    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");

    expect($this->gallery->fresh()->currentVersion->version)->toBe(2);
});

it('closes the gate on withdrawal and leaves the version standing', function () {
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");
    $this->delete("/galerien/{$this->gallery->public_id}/veroeffentlichung")->assertRedirect();

    expect($this->gallery->fresh()->unpublished_at)->not->toBeNull()
        ->and($this->gallery->fresh()->versions()->count())->toBe(1);
});

it('keeps publishing away from a role that may not show things to clients', function () {
    $shooter = User::factory()->withMfa()->create(['role' => StaffRole::SecondShooter]);

    $this->actingAs($shooter, 'staff')
        ->post("/galerien/{$this->gallery->public_id}/veroeffentlichen")
        ->assertForbidden();
});

it('shuts guests out on withdrawal without touching the standing version', function () {
    $this->post("/galerien/{$this->gallery->public_id}/veroeffentlichen");
    $this->delete("/galerien/{$this->gallery->public_id}/veroeffentlichung");

    useSurface(Surface::PORTAL);

    $this->get("/galerien/{$this->gallery->public_id}/beteiligung")->assertNotFound();
});
