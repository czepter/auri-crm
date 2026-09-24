<?php

use App\Gallery\Models\AssetComment;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::PORTAL));

function engagedGallery(): Gallery
{
    $gallery = Gallery::factory()->create();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g/a.jpg', 'filename' => 'a.jpg']);
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g/b.jpg', 'filename' => 'b.jpg']);
    $gallery->fresh()->publish();

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

// G-01 (#229): two roles, one screen. Without this the couple appear to the
// studio as anonymous strangers on their own gallery.
it('signs a client\'s comment with their name and a guest\'s with Gast', function () {
    $project = App\Crm\Models\ClientCase::factory()->create(['portal_visible' => true]);
    $contact = App\Crm\Models\Contact::factory()->create(['name' => 'Viola Neumann']);
    $project->attachContact($contact, App\Crm\Enums\CaseContactRole::Primary);

    $gallery = Gallery::factory()->for($project, 'case')->create();
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g/a.jpg', 'filename' => 'a.jpg']);
    $gallery->fresh()->publish();

    // The guest first, while nobody is signed in.
    test()->postJson("/galerien/{$gallery->public_id}/kommentar", [
        'key' => 'galleries/g/a.jpg', 'body' => 'So schön!',
    ])->assertCreated();

    test()->actingAs($contact, 'portal')->postJson("/galerien/{$gallery->public_id}/kommentar", [
        'key' => 'galleries/g/a.jpg', 'body' => 'Das bitte groß ausbelichten.',
    ])->assertCreated();

    $comments = test()->getJson("/galerien/{$gallery->public_id}/beteiligung")->json('comments');

    expect(collect($comments)->pluck('author')->all())->toBe(['Gast', 'Viola Neumann'])
        ->and(collect($comments)->pluck('own')->all())->toBe([false, true]);
});

// Signed in to your own portal, then following a link a friend forwarded:
// that is a guest on somebody else's gallery, and reads as one.
it('treats a signed-in client as a guest on a gallery that is not theirs', function () {
    $gallery = engagedGallery();

    test()->actingAs(App\Crm\Models\Contact::factory()->create(), 'portal')
        ->postJson("/galerien/{$gallery->public_id}/kommentar", [
            'key' => 'galleries/g/a.jpg', 'body' => 'Hallo.',
        ])->assertCreated();

    expect(AssetComment::sole()->contact_id)->toBeNull();
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

// The rail appends the posted row without a reload, so it has to come back
// in the shape the read route returns — otherwise it renders nameless.
it('returns a posted comment in the same shape it reads them', function () {
    $gallery = engagedGallery();

    $posted = test()->postJson("/galerien/{$gallery->public_id}/kommentar", [
        'key' => 'galleries/g/a.jpg', 'body' => 'So schön!',
    ])->assertCreated()->json('comment');

    $read = test()->getJson("/galerien/{$gallery->public_id}/beteiligung")->json('comments.0');

    expect($posted)->toBe($read);
});
