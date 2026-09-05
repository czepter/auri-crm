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
    [$me, $pass] = confirmedGuest($version->gallery);

    $response = $this->postJson(
        "/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg",
        ['guest_pass' => $pass],
    );

    $response->assertOk()->assertJson(['favorited' => true]);

    expect(AssetFavorite::where('guest_identifier', $me->identifier)->count())->toBe(1);
});

it('persists the mark: a later request sees the same favorite', function () {
    $version = publishedVersionWithAssets();
    [$me, $pass] = confirmedGuest($version->gallery);

    $this->postJson(
        "/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg",
        ['guest_pass' => $pass],
    )->assertOk();

    $this->getJson("/api/gallery-versions/{$version->public_id}/favorites?guest_pass={$pass}")
        ->assertOk()
        ->assertJson(['favorited' => ['galleries/g1/a.jpg']]);
});

it('is idempotent: toggling twice leaves no favorite behind and no duplicate rows', function () {
    $version = publishedVersionWithAssets();
    [$me, $pass] = confirmedGuest($version->gallery);
    $path = "/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg";

    $this->postJson($path, ['guest_pass' => $pass])->assertOk()->assertJson(['favorited' => true]);
    $this->postJson($path, ['guest_pass' => $pass])->assertOk()->assertJson(['favorited' => false]);

    expect(AssetFavorite::where('guest_identifier', $me->identifier)->count())->toBe(0);
});

it('scopes favorites per guest: one guest\'s mark is invisible to another', function () {
    $version = publishedVersionWithAssets();
    [$me, $pass] = confirmedGuest($version->gallery);
    $path = "/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg";

    $this->postJson($path, ['guest_pass' => $pass])->assertOk();

    $this->getJson("/api/gallery-versions/{$version->public_id}/favorites?guest_id=guest-2")
        ->assertOk()
        ->assertJson(['favorited' => []]);

    $this->getJson("/api/gallery-versions/{$version->public_id}/favorites?guest_pass={$pass}")
        ->assertOk()
        ->assertJson(['favorited' => ['galleries/g1/a.jpg']]);
});

it('scopes favorites per asset: favoriting one key leaves a sibling key untouched', function () {
    $version = publishedVersionWithAssets();
    [$me, $pass] = confirmedGuest($version->gallery);

    $this->postJson(
        "/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg",
        ['guest_pass' => $pass],
    )->assertOk();

    $this->getJson("/api/gallery-versions/{$version->public_id}/favorites?guest_pass={$pass}")
        ->assertOk()
        ->assertJson(['favorited' => ['galleries/g1/a.jpg']]);
});

it('404s a key that is not in this version\'s own assets', function () {
    $version = publishedVersionWithAssets();
    [$me, $pass] = confirmedGuest($version->gallery);

    $this->postJson(
        "/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/not-listed.jpg",
        ['guest_pass' => $pass],
    )->assertNotFound();
});

// Ohne bestaetigte Adresse kein Herz: sonst haette die Markierung keinen
// Absender und waere auf dem naechsten Geraet verloren.
it('refuses a toggle from someone who has not confirmed an address', function () {
    $version = publishedVersionWithAssets();

    $this->postJson("/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg", [])
        ->assertForbidden();

    $this->postJson(
        "/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg",
        ['guest_pass' => 'erfunden.erfunden.99999999999.erfunden'],
    )->assertForbidden();
});

it('returns an empty list for a pass-less read rather than every guest\'s favorites', function () {
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
    [$me, $pass] = confirmedGuest($version->gallery);
    $staff = User::factory()->create(['role' => StaffRole::Owner]);

    $this->postJson(
        "/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg",
        ['guest_pass' => $pass],
    )->assertOk()->assertJson(['favorited' => true]);

    expect(InAppNotification::where('notifiable_type', $staff->getMorphClass())->where('notifiable_id', $staff->id)->count())->toBe(1);
});

it('does not notify the studio when a favorite is removed', function () {
    $version = publishedVersionWithAssets();
    [$me, $pass] = confirmedGuest($version->gallery);
    $staff = User::factory()->create(['role' => StaffRole::Owner]);
    $path = "/api/gallery-versions/{$version->public_id}/favorites/galleries/g1/a.jpg";

    $this->postJson($path, ['guest_pass' => $pass])->assertOk()->assertJson(['favorited' => true]);
    $this->postJson($path, ['guest_pass' => $pass])->assertOk()->assertJson(['favorited' => false]);

    expect(InAppNotification::where('notifiable_type', $staff->getMorphClass())->where('notifiable_id', $staff->id)->count())->toBe(1);
});
