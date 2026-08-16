<?php

use App\Gallery\Models\AssetComment;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::PORTAL));

function engagedGallery(): Gallery
{
    $gallery = Gallery::factory()->create(['assets' => [
        ['key' => 'galleries/g/a.jpg'],
        ['key' => 'galleries/g/b.jpg'],
    ]]);
    $gallery->publish();

    return $gallery->fresh();
}

// The guest identity is the portal session now, not a string a browser
// minted into localStorage — the reason it had to be one was that the grid
// used to live on another origin.
it('marks and unmarks a picture for the visitor holding this session', function () {
    $gallery = engagedGallery();

    test()->postJson("/galerien/{$gallery->public_id}/favorit", ['key' => 'galleries/g/a.jpg'])
        ->assertOk()
        ->assertJson(['favorited' => true]);

    expect(AssetFavorite::count())->toBe(1);

    test()->getJson("/galerien/{$gallery->public_id}/beteiligung")
        ->assertOk()
        ->assertJson(['favorited' => ['galleries/g/a.jpg']]);

    test()->postJson("/galerien/{$gallery->public_id}/favorit", ['key' => 'galleries/g/a.jpg'])
        ->assertOk()
        ->assertJson(['favorited' => false]);

    expect(AssetFavorite::count())->toBe(0);
});

it('never shows one visitor another visitor\'s marks', function () {
    $gallery = engagedGallery();

    test()->postJson("/galerien/{$gallery->public_id}/favorit", ['key' => 'galleries/g/a.jpg'])->assertOk();

    expect(AssetFavorite::count())->toBe(1);

    // A second browser: no session, so no guest id in common.
    test()->flushSession();

    test()->getJson("/galerien/{$gallery->public_id}/beteiligung")
        ->assertOk()
        ->assertJson(['favorited' => []]);
});

it('takes a comment and hands it back to everyone', function () {
    $gallery = engagedGallery();

    test()->postJson("/galerien/{$gallery->public_id}/kommentar", [
        'key' => 'galleries/g/a.jpg',
        'body' => 'Das bitte groß ausbelichten.',
    ])->assertCreated();

    expect(AssetComment::count())->toBe(1);

    test()->flushSession();

    $comments = test()->getJson("/galerien/{$gallery->public_id}/beteiligung")
        ->assertOk()
        ->json('comments');

    expect($comments)->toHaveCount(1)
        ->and($comments[0]['body'])->toBe('Das bitte groß ausbelichten.');
});

it('refuses a key the published version does not have', function () {
    $gallery = engagedGallery();

    test()->postJson("/galerien/{$gallery->public_id}/favorit", ['key' => 'galleries/g/x.jpg'])
        ->assertNotFound();
});

// Somebody who has not got past the front door has nothing to favourite.
it('sends a visitor who has not cleared the view gate back to it', function () {
    $gallery = engagedGallery();
    $gallery->rotateViewPassword('basthorst2026');

    test()->get("/galerien/{$gallery->public_id}/beteiligung")
        ->assertRedirect("/galerien/{$gallery->public_id}");

    test()->post("/galerien/{$gallery->public_id}/favorit", ['key' => 'galleries/g/a.jpg'])
        ->assertRedirect("/galerien/{$gallery->public_id}");

    expect(AssetFavorite::count())->toBe(0);
});
