<?php

use App\Enums\StaffRole;
use App\Gallery\Models\AssetComment;
use App\Gallery\Models\Gallery;
use App\Models\User;
use App\Notifications\Models\InAppNotification;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::API));

function publishedVersionWithCommentableAssets(array $keys = ['galleries/g1/a.jpg', 'galleries/g1/b.jpg'])
{
    $gallery = Gallery::factory()->create(['assets' => collect($keys)
        ->map(fn (string $key) => ['key' => $key, 'width' => 200, 'height' => 100])
        ->all(),
    ]);

    return $gallery->publish();
}

it('leaves a comment on an asset a guest has never commented on before', function () {
    $version = publishedVersionWithCommentableAssets();

    $response = $this->postJson("/api/gallery-versions/{$version->public_id}/comments", [
        'guest_id' => 'guest-1',
        'asset_key' => 'galleries/g1/a.jpg',
        'body' => 'Wunderschönes Licht hier.',
    ]);

    $response->assertCreated()->assertJson(['comment' => ['body' => 'Wunderschönes Licht hier.']]);

    expect(AssetComment::where('guest_identifier', 'guest-1')->count())->toBe(1);
});

it('lists every comment on one asset, oldest first', function () {
    $version = publishedVersionWithCommentableAssets();

    $this->postJson("/api/gallery-versions/{$version->public_id}/comments", [
        'guest_id' => 'guest-1', 'asset_key' => 'galleries/g1/a.jpg', 'body' => 'Erster Kommentar.',
    ])->assertCreated();

    $this->postJson("/api/gallery-versions/{$version->public_id}/comments", [
        'guest_id' => 'guest-2', 'asset_key' => 'galleries/g1/a.jpg', 'body' => 'Zweiter Kommentar.',
    ])->assertCreated();

    $response = $this->getJson("/api/gallery-versions/{$version->public_id}/comments?asset_key=".urlencode('galleries/g1/a.jpg'));

    $response->assertOk()->assertJsonPath('comments.0.body', 'Erster Kommentar.')
        ->assertJsonPath('comments.1.body', 'Zweiter Kommentar.');
});

it('scopes comments per asset: commenting on one key leaves a sibling key untouched', function () {
    $version = publishedVersionWithCommentableAssets();

    $this->postJson("/api/gallery-versions/{$version->public_id}/comments", [
        'guest_id' => 'guest-1', 'asset_key' => 'galleries/g1/a.jpg', 'body' => 'Nur für a.jpg.',
    ])->assertCreated();

    $this->getJson("/api/gallery-versions/{$version->public_id}/comments?asset_key=".urlencode('galleries/g1/b.jpg'))
        ->assertOk()
        ->assertJson(['comments' => []]);
});

it('allows more than one comment from the same guest on the same asset', function () {
    $version = publishedVersionWithCommentableAssets();
    $payload = ['guest_id' => 'guest-1', 'asset_key' => 'galleries/g1/a.jpg', 'body' => 'Noch einer.'];

    $this->postJson("/api/gallery-versions/{$version->public_id}/comments", $payload)->assertCreated();
    $this->postJson("/api/gallery-versions/{$version->public_id}/comments", $payload)->assertCreated();

    expect(AssetComment::where('guest_identifier', 'guest-1')->count())->toBe(2);
});

it('404s a key that is not in this version\'s own assets', function () {
    $version = publishedVersionWithCommentableAssets();

    $this->postJson("/api/gallery-versions/{$version->public_id}/comments", [
        'guest_id' => 'guest-1', 'asset_key' => 'galleries/g1/not-listed.jpg', 'body' => 'Egal.',
    ])->assertNotFound();
});

it('requires a guest_id, an asset_key and a body to comment', function () {
    $version = publishedVersionWithCommentableAssets();

    $this->postJson("/api/gallery-versions/{$version->public_id}/comments", [])->assertStatus(422);
});

it('returns an empty list for an unknown or missing asset_key rather than every comment on the version', function () {
    $version = publishedVersionWithCommentableAssets();

    AssetComment::factory()->for($version, 'galleryVersion')->create(['asset_key' => 'galleries/g1/a.jpg']);

    $this->getJson("/api/gallery-versions/{$version->public_id}/comments")
        ->assertOk()
        ->assertJson(['comments' => []]);
});

it('notifies every staff user of the tenant through the unread stream when a comment lands', function () {
    $version = publishedVersionWithCommentableAssets();
    $staffOne = User::factory()->create(['role' => StaffRole::Owner]);
    $staffTwo = User::factory()->create(['role' => StaffRole::Photographer]);

    $this->postJson("/api/gallery-versions/{$version->public_id}/comments", [
        'guest_id' => 'guest-1', 'asset_key' => 'galleries/g1/a.jpg', 'body' => 'Bitte ins Album.',
    ])->assertCreated();

    expect(InAppNotification::where('notifiable_type', $staffOne->getMorphClass())->where('notifiable_id', $staffOne->id)->count())->toBe(1);
    expect(InAppNotification::where('notifiable_type', $staffTwo->getMorphClass())->where('notifiable_id', $staffTwo->id)->count())->toBe(1);
});
