<?php

use App\Enums\StaffRole;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;
use App\Models\User;
use App\Notifications\Models\InAppNotification;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::API));

function publishedVersionWithAssets(array $keys = ['galleries/g1/a.jpg', 'galleries/g1/b.jpg'])
{
    $gallery = Gallery::factory()->create();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    foreach ($keys as $key) {
        Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => $key, 'filename' => basename($key), 'width' => 200, 'height' => 100]);
    }

    return $gallery->fresh()->publish();
}

it('favorites an asset a guest has never marked before', function () {
    $version = publishedVersionWithAssets();

    $response = $this->postJson(
        "/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg",
        ['guest_id' => 'guest-1'],
    );

    $response->assertOk()->assertJson(['favorited' => true]);

    expect(AssetFavorite::where('guest_identifier', 'guest-1')->count())->toBe(1);
});

it('persists the mark: a later request sees the same favorite', function () {
    $version = publishedVersionWithAssets();

    $this->postJson(
        "/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg",
        ['guest_id' => 'guest-1'],
    )->assertOk();

    $this->getJson("/api/gallery-versions/{$version->public_id}/favorites?guest_id=guest-1")
        ->assertOk()
        ->assertJson(['favorited' => ['galleries/g1/a.jpg']]);
});

it('is idempotent: toggling twice leaves no favorite behind and no duplicate rows', function () {
    $version = publishedVersionWithAssets();
    $path = "/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg";

    $this->postJson($path, ['guest_id' => 'guest-1'])->assertOk()->assertJson(['favorited' => true]);
    $this->postJson($path, ['guest_id' => 'guest-1'])->assertOk()->assertJson(['favorited' => false]);

    expect(AssetFavorite::where('guest_identifier', 'guest-1')->count())->toBe(0);
});

it('scopes favorites per guest: one guest\'s mark is invisible to another', function () {
    $version = publishedVersionWithAssets();
    $path = "/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg";

    $this->postJson($path, ['guest_id' => 'guest-1'])->assertOk();

    $this->getJson("/api/gallery-versions/{$version->public_id}/favorites?guest_id=guest-2")
        ->assertOk()
        ->assertJson(['favorited' => []]);

    $this->getJson("/api/gallery-versions/{$version->public_id}/favorites?guest_id=guest-1")
        ->assertOk()
        ->assertJson(['favorited' => ['galleries/g1/a.jpg']]);
});

it('scopes favorites per asset: favoriting one key leaves a sibling key untouched', function () {
    $version = publishedVersionWithAssets();

    $this->postJson(
        "/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg",
        ['guest_id' => 'guest-1'],
    )->assertOk();

    $this->getJson("/api/gallery-versions/{$version->public_id}/favorites?guest_id=guest-1")
        ->assertOk()
        ->assertJson(['favorited' => ['galleries/g1/a.jpg']]);
});

it('404s a key that is not in this version\'s own assets', function () {
    $version = publishedVersionWithAssets();

    $this->postJson(
        "/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/not-listed.jpg",
        ['guest_id' => 'guest-1'],
    )->assertNotFound();
});

it('requires a guest_id to toggle', function () {
    $version = publishedVersionWithAssets();

    $this->postJson("/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg", [])
        ->assertStatus(422);
});

it('returns an empty list for a guest_id-less read rather than every guest\'s favorites', function () {
    $version = publishedVersionWithAssets();

    AssetFavorite::factory()->for($version, 'galleryVersion')->create([
        'asset_key' => 'galleries/g1/a.jpg',
        'guest_identifier' => 'someone-else',
    ]);

    $this->getJson("/api/gallery-versions/{$version->public_id}/favorites")
        ->assertOk()
        ->assertJson(['favorited' => []]);
});

it('notifies every staff user of the tenant through the unread stream when a favorite is added', function () {
    $version = publishedVersionWithAssets();
    $staff = User::factory()->create(['role' => StaffRole::Owner]);

    $this->postJson(
        "/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg",
        ['guest_id' => 'guest-1'],
    )->assertOk()->assertJson(['favorited' => true]);

    expect(InAppNotification::where('notifiable_type', $staff->getMorphClass())->where('notifiable_id', $staff->id)->count())->toBe(1);
});

it('does not notify the studio when a favorite is removed', function () {
    $version = publishedVersionWithAssets();
    $staff = User::factory()->create(['role' => StaffRole::Owner]);
    $path = "/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg";

    $this->postJson($path, ['guest_id' => 'guest-1'])->assertOk()->assertJson(['favorited' => true]);
    $this->postJson($path, ['guest_id' => 'guest-1'])->assertOk()->assertJson(['favorited' => false]);

    expect(InAppNotification::where('notifiable_type', $staff->getMorphClass())->where('notifiable_id', $staff->id)->count())->toBe(1);
});
