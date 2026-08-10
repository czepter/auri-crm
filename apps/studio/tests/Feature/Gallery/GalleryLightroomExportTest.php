<?php

use App\Enums\StaffRole;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exports every favorited asset\'s original filename, one per line, deduplicated', function () {
    $gallery = Gallery::factory()->create(['title' => 'Hochzeit Meier', 'assets' => [
        ['key' => 'originals/asset1/IMG_4788.jpg', 'width' => 100, 'height' => 100],
        ['key' => 'originals/asset2/IMG_4877.jpg', 'width' => 100, 'height' => 100],
        ['key' => 'originals/asset3/IMG_5107.jpg', 'width' => 100, 'height' => 100],
    ]]);
    $version = $gallery->publish();

    AssetFavorite::factory()->for($version, 'galleryVersion')->create(['asset_key' => 'originals/asset1/IMG_4788.jpg', 'guest_identifier' => 'guest-1']);
    // A second guest favoriting the same asset must not duplicate the line.
    AssetFavorite::factory()->for($version, 'galleryVersion')->create(['asset_key' => 'originals/asset1/IMG_4788.jpg', 'guest_identifier' => 'guest-2']);
    AssetFavorite::factory()->for($version, 'galleryVersion')->create(['asset_key' => 'originals/asset2/IMG_4877.jpg', 'guest_identifier' => 'guest-1']);

    $staff = staffMember(StaffRole::Owner);

    $response = $this->actingAs($staff, 'staff')->get("/galerien/{$gallery->public_id}/lightroom-export");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/plain');
    expect($response->headers->get('Content-Disposition'))->toContain('.txt');
    expect(explode("\n", $response->getContent()))->toBe(['IMG_4788.jpg', 'IMG_4877.jpg']);
});

it('exports nothing for a version with no favorites', function () {
    $gallery = Gallery::factory()->create(['assets' => [
        ['key' => 'originals/asset1/IMG_4788.jpg', 'width' => 100, 'height' => 100],
    ]]);
    $gallery->publish();

    $staff = staffMember(StaffRole::Owner);

    $response = $this->actingAs($staff, 'staff')->get("/galerien/{$gallery->public_id}/lightroom-export");

    $response->assertOk();
    expect($response->getContent())->toBe('');
});

it('404s a gallery that has never been published', function () {
    $gallery = Gallery::factory()->create();
    $staff = staffMember(StaffRole::Owner);

    $this->actingAs($staff, 'staff')->get("/galerien/{$gallery->public_id}/lightroom-export")->assertNotFound();
});

it('rejects a staff member without media-management capability', function () {
    $gallery = Gallery::factory()->create();
    $gallery->publish();
    $staff = staffMember(StaffRole::Assistant);

    $this->actingAs($staff, 'staff')->get("/galerien/{$gallery->public_id}/lightroom-export")->assertForbidden();
});

it('rejects an unauthenticated request', function () {
    $gallery = Gallery::factory()->create();
    $gallery->publish();

    $this->get("/galerien/{$gallery->public_id}/lightroom-export")->assertRedirect('/login');
});
