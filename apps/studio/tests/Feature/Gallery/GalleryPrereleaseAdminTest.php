<?php

use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryPrereleaseSignup;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
    $this->gallery = Gallery::factory()->create();
});

it('starts pre-release', function () {
    $this->post("/galerien/{$this->gallery->public_id}/pre-release")->assertRedirect();

    expect($this->gallery->fresh()->prerelease_at)->not->toBeNull();
});

it('stops pre-release', function () {
    $this->gallery->forceFill(['prerelease_at' => now()])->save();

    $this->delete("/galerien/{$this->gallery->public_id}/pre-release")->assertRedirect();

    expect($this->gallery->fresh()->prerelease_at)->toBeNull();
});

// Personal-data fix round 1: GalleryPrereleaseSignup's own docblock — the
// waiting list belongs to this pre-release window, not to the gallery for
// ever.
it('deletes every pending signup when pre-release stops', function () {
    $this->gallery->forceFill(['prerelease_at' => now()])->save();
    GalleryPrereleaseSignup::create(['gallery_id' => $this->gallery->id, 'email' => 'anna@example.test']);

    $this->delete("/galerien/{$this->gallery->public_id}/pre-release");

    expect(GalleryPrereleaseSignup::count())->toBe(0);
});

it('refuses to start pre-release once the gallery is published', function () {
    $set = GallerySet::where('gallery_id', $this->gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $this->gallery->id, 'gallery_set_id' => $set->id, 'key' => 'g/a.jpg']);
    $this->gallery->fresh()->publish();

    $this->post("/galerien/{$this->gallery->public_id}/pre-release")->assertSessionHasErrors('gallery');

    expect($this->gallery->fresh()->prerelease_at)->toBeNull();
});

it('refuses to start pre-release on an external gallery', function () {
    $external = Gallery::factory()->create(['is_external' => true, 'external_url' => 'https://legacy.example/g/1']);

    $this->post("/galerien/{$external->public_id}/pre-release")->assertSessionHasErrors('gallery');
});

// Weighed against `publish` (ManagePortalAccess), not `manageMedia`: this
// makes the gallery's title reachable by anyone holding the link, same as
// deciding a gallery is presentable at all.
it('keeps starting pre-release away from a role that may not show things to clients', function () {
    $editor = User::factory()->withMfa()->create(['role' => StaffRole::Contributor]);

    $this->actingAs($editor, 'staff')
        ->post("/galerien/{$this->gallery->public_id}/pre-release")
        ->assertForbidden();
});

it('runs a pre-release in its own gallery and leaves the source alone', function () {
    $this->gallery->update(['title' => 'Hochzeit']);

    $response = $this->post("/galerien/{$this->gallery->public_id}/pre-release", ['own' => true]);

    $copy = Gallery::query()->where('title', 'Hochzeit Vorabversion')->firstOrFail();
    $response->assertRedirect("/galerien/{$copy->public_id}");

    expect($copy->prerelease_at)->not->toBeNull()
        ->and($copy->case_id)->toBe($this->gallery->case_id)
        ->and($this->gallery->fresh()->prerelease_at)->toBeNull();
});
