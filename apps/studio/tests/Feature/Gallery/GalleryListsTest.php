<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryList;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::PORTAL));

/** @return array{0: Gallery, 1: Contact} */
function clientGallery(): array
{
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $gallery = Gallery::factory()->for($project)->create([
        'title' => 'Verlobungsshooting',
        'assets' => [
            ['key' => 'galleries/g/a.jpg'],
            ['key' => 'galleries/g/b.jpg'],
            ['key' => 'galleries/g/c.jpg'],
        ],
    ]);
    $gallery->publish();

    return [$gallery->fresh(), $contact];
}

// One screen, two audiences: the couple's own half rides under the pictures
// rather than on a second page.
it('gives the client their own half of the gallery screen', function () {
    [$gallery, $contact] = clientGallery();

    $props = test()->actingAs($contact, 'portal')
        ->get("/galerien/{$gallery->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['assets'])->toHaveCount(3)
        ->and($props['gates']['viewPassword'])->toBeFalse()
        ->and($props['guests'])->not->toBeNull();
});

it('sends a guest the pictures and none of the couple\'s half', function () {
    [$gallery] = clientGallery();

    $props = props("/galerien/{$gallery->public_id}");

    expect($props['assets'])->toHaveCount(3)
        ->and($props['lists'])->toBeNull()
        ->and($props['gates'])->toBeNull()
        ->and($props['guests'])->toBeNull();
});

// Signed in to your own portal is not the same as being the client of THIS
// gallery — somebody following a forwarded link is a guest on it.
it('refuses the couple\'s own actions to somebody on another booking', function () {
    [$gallery] = clientGallery();

    test()->actingAs(Contact::factory()->create(), 'portal')
        ->post("/galerien/{$gallery->public_id}/eure-auswahl/listen", ['name' => 'Album'])
        ->assertNotFound();
});

// Named lists belong to the signed-in client, not to a localStorage string.
it('makes, renames and deletes a named list', function () {
    [$gallery, $contact] = clientGallery();
    $base = "/galerien/{$gallery->public_id}/eure-auswahl";

    test()->actingAs($contact, 'portal')->post("{$base}/listen", ['name' => 'Für die Eltern']);

    $list = GalleryList::query()->whereNotNull('name')->sole();
    expect($list->name)->toBe('Für die Eltern');

    test()->patch("{$base}/listen/{$list->public_id}", ['name' => 'Album']);
    expect($list->fresh()->name)->toBe('Album');

    test()->delete("{$base}/listen/{$list->public_id}");
    expect(GalleryList::query()->whereNotNull('name')->count())->toBe(0);
});

// The heart is a list without a name, made on first use rather than at sign-in.
it('puts a picture on the unnamed list and takes it off again', function () {
    [$gallery, $contact] = clientGallery();
    $base = "/galerien/{$gallery->public_id}/eure-auswahl";

    test()->actingAs($contact, 'portal')->post("{$base}/listen/herz/bild", ['key' => 'galleries/g/a.jpg']);

    $heart = GalleryList::query()->whereNull('name')->sole();
    expect($heart->items)->toHaveCount(1);

    test()->post("{$base}/listen/herz/bild", ['key' => 'galleries/g/a.jpg']);
    expect($heart->fresh()->items)->toHaveCount(0);
});

it('refuses to delete the heart, which is a control rather than a list', function () {
    [$gallery, $contact] = clientGallery();
    $base = "/galerien/{$gallery->public_id}/eure-auswahl";

    test()->actingAs($contact, 'portal')->post("{$base}/listen/herz/bild", ['key' => 'galleries/g/a.jpg']);

    $heart = GalleryList::query()->whereNull('name')->sole();

    test()->delete("{$base}/listen/{$heart->public_id}")->assertNotFound();
});

it('refuses a key the published version does not have', function () {
    [$gallery, $contact] = clientGallery();

    test()->actingAs($contact, 'portal')
        ->post("/galerien/{$gallery->public_id}/eure-auswahl/listen/herz/bild", ['key' => 'galleries/g/x.jpg'])
        ->assertNotFound();
});

// A list attached to a version would be orphaned by the next publish(); this
// is the whole reason it hangs off the gallery.
it('keeps a list across a re-publish', function () {
    [$gallery, $contact] = clientGallery();

    test()->actingAs($contact, 'portal')
        ->post("/galerien/{$gallery->public_id}/eure-auswahl/listen/herz/bild", ['key' => 'galleries/g/a.jpg']);

    $gallery->update(['assets' => [['key' => 'galleries/g/a.jpg'], ['key' => 'galleries/g/d.jpg']]]);
    $gallery->publish();

    $props = test()->get("/galerien/{$gallery->public_id}")->assertOk()->viewData('page')['props'];

    expect($props['lists'][0]['keys'])->toBe(['galleries/g/a.jpg']);
});

it('leaves out a key the newest version dropped, without breaking the list', function () {
    [$gallery, $contact] = clientGallery();
    $base = "/galerien/{$gallery->public_id}/eure-auswahl";

    test()->actingAs($contact, 'portal')->post("{$base}/listen/herz/bild", ['key' => 'galleries/g/a.jpg']);
    test()->post("{$base}/listen/herz/bild", ['key' => 'galleries/g/b.jpg']);

    $gallery->update(['assets' => [['key' => 'galleries/g/b.jpg']]]);
    $gallery->publish();

    $props = test()->get("/galerien/{$gallery->public_id}")->assertOk()->viewData('page')['props'];

    expect($props['lists'][0]['keys'])->toBe(['galleries/g/b.jpg']);
});

// Rotating is the only real answer to a link that travelled too far, and the
// version bump is what turns out the guests who are already inside.
it('rotates the view password and invalidates the guests already in', function () {
    [$gallery, $contact] = clientGallery();

    $before = $gallery->view_password_version;

    test()->actingAs($contact, 'portal')
        ->post("/galerien/{$gallery->public_id}/eure-auswahl/tore/ansicht", ['secret' => 'herbstlicht'])
        ->assertRedirect()
        ->assertSessionHas('secret', 'herbstlicht');

    $fresh = $gallery->fresh();

    expect($fresh->requiresViewPassword())->toBeTrue()
        ->and($fresh->checkViewPassword('herbstlicht'))->toBeTrue()
        ->and($fresh->view_password_version)->toBe($before + 1);
});

it('clears a gate when rotated with nothing', function () {
    [$gallery, $contact] = clientGallery();
    $gallery->rotateViewPassword('herbstlicht');

    test()->actingAs($contact, 'portal')
        ->post("/galerien/{$gallery->public_id}/eure-auswahl/tore/ansicht", ['secret' => null]);

    expect($gallery->fresh()->requiresViewPassword())->toBeFalse();
});

// Readable exactly once, in the response to the request that set it — the
// column carries the 'hashed' cast, so there is no second chance for anyone,
// the studio included.
it('shows a fresh secret once and never again', function () {
    [$gallery, $contact] = clientGallery();
    $url = "/galerien/{$gallery->public_id}/eure-auswahl";

    test()->actingAs($contact, 'portal')->post("{$url}/tore/ansicht", ['secret' => 'herbstlicht']);

    $screen = "/galerien/{$gallery->public_id}";

    $first = test()->get($screen)->assertOk()->viewData('page')['props'];
    expect($first['flash']['secret'])->toBe('herbstlicht');

    $second = test()->get($screen)->assertOk()->viewData('page')['props'];

    expect(json_encode($second))->not->toContain('herbstlicht')
        ->and($second['gates']['viewPassword'])->toBeTrue();
});

// Nothing counted a visit before; DownloadEvent only ever counted downloads.
it('counts a visit once per visitor session', function () {
    [$gallery] = clientGallery();

    test()->get("/galerien/{$gallery->public_id}")->assertOk();
    test()->get("/galerien/{$gallery->public_id}")->assertOk();

    expect($gallery->fresh()->view_count)->toBe(1);
});
