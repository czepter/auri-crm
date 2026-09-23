<?php

use App\Crm\Enums\CaseContactRole;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Gallery\Models\GalleryView;
use App\Media\Models\Asset;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::PORTAL));

function openableGallery(array $attributes = []): Gallery
{
    $gallery = Gallery::factory()->create($attributes);
    $set = GallerySet::where('gallery_id', $gallery->id)->first();
    Asset::factory()->create(['gallery_id' => $gallery->id, 'gallery_set_id' => $set->id, 'key' => 'galleries/g1/a.jpg', 'width' => 4000, 'height' => 3000]);
    $gallery->fresh()->publish();

    return $gallery->fresh();
}

// C-21 (#256): the first opening per visitor per day, not every page load —
// a couple showing their parents the pictures is one visit, not forty.
it('records one view per visitor and day, however often the page is opened', function () {
    $gallery = openableGallery();

    test()->get("/galerien/{$gallery->public_id}")->assertOk();
    test()->get("/galerien/{$gallery->public_id}")->assertOk();

    expect(GalleryView::query()->where('gallery_id', $gallery->id)->count())->toBe(1);
});

it('names the client where one is signed in, and leaves a guest nameless', function () {
    $project = ClientCase::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, CaseContactRole::Primary);

    $theirs = openableGallery(['case_id' => $project->id]);

    test()->actingAs($contact, 'portal')->get("/galerien/{$theirs->public_id}")->assertOk();

    expect(GalleryView::query()->where('gallery_id', $theirs->id)->value('contact_id'))->toBe($contact->id);

    $guestGallery = openableGallery();
    test()->get("/galerien/{$guestGallery->public_id}")->assertOk();

    expect(GalleryView::query()->where('gallery_id', $guestGallery->id)->value('contact_id'))->toBeNull();
});

// A gate that is closed is not a visit: nothing was seen.
it('records nothing while the gate is still closed', function () {
    $gallery = openableGallery();
    $gallery->rotateViewPassword('basthorst2026');

    test()->get("/galerien/{$gallery->public_id}")->assertOk();

    expect(GalleryView::query()->count())->toBe(0);
});
