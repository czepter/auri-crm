<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Gallery\Models\Gallery;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => useSurface(Surface::PORTAL));

function publishedGallery(array $attributes = []): Gallery
{
    $gallery = Gallery::factory()->create([...$attributes, 'assets' => [
        ['key' => 'galleries/g1/a.jpg', 'width' => 4000, 'height' => 3000],
        ['key' => 'galleries/g1/b.jpg', 'width' => 3000, 'height' => 4000],
    ]]);
    $gallery->publish();

    return $gallery->fresh();
}

// P-08's whole point: the gate used to end on a screen that said the grid
// was "noch nicht verfügbar" while the real one sat on another host.
it('serves the grid on the same page as the gate, once unlocked', function () {
    $gallery = publishedGallery();

    $props = props("/galerien/{$gallery->public_id}");

    expect($props['unlocked'])->toBeTrue()
        ->and($props['assets'])->toHaveCount(2)
        ->and($props['assets'][0]['src'])->toContain('/galerie-bilder/');
});

it('hands out no asset list at all while the gate is closed', function () {
    $gallery = publishedGallery();
    $gallery->rotateViewPassword('basthorst2026');

    $props = props("/galerien/{$gallery->public_id}");

    expect($props['unlocked'])->toBeFalse()
        ->and($props['assets'])->toBeNull();
});

it('shows the grid to the client whose booking the gallery belongs to, without the password', function () {
    $project = Project::factory()->create(['portal_visible' => true]);
    $contact = Contact::factory()->create();
    $project->attachContact($contact, ProjectContactRole::Primary);

    $gallery = publishedGallery(['project_id' => $project->id]);
    $gallery->rotateViewPassword('basthorst2026');

    $props = test()->actingAs($contact, 'portal')
        ->get("/galerien/{$gallery->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['unlocked'])->toBeTrue();
});

it('still asks a signed-in client for the password on someone else\'s gallery', function () {
    $gallery = publishedGallery(['project_id' => Project::factory()->create(['portal_visible' => true])->id]);
    $gallery->rotateViewPassword('basthorst2026');

    $props = test()->actingAs(Contact::factory()->create(), 'portal')
        ->get("/galerien/{$gallery->public_id}")
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['unlocked'])->toBeFalse();
});

it('serves image bytes on the portal surface once the gate is cleared', function () {
    Storage::fake('s3');
    $image = imagecreatetruecolor(200, 100);
    ob_start();
    imagejpeg($image);
    Storage::disk('s3')->put('galleries/g1/a.jpg', ob_get_clean());

    $version = publishedGallery()->currentVersion;

    test()->get("/galerie-bilder/{$version->public_id}/galleries/g1/a.jpg?w=800&fmt=jpeg")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

it('redirects the image route back to the gate when the gate is closed', function () {
    $gallery = publishedGallery();
    $gallery->rotateViewPassword('basthorst2026');
    $version = $gallery->currentVersion;

    test()->get("/galerie-bilder/{$version->public_id}/galleries/g1/a.jpg?w=800&fmt=jpeg")
        ->assertRedirect("/galerien/{$gallery->public_id}");
});
