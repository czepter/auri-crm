<?php

use App\Crm\Models\Project;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');

    $this->gallery = Gallery::factory()->for(Project::factory())->create();
    $this->set = GallerySet::factory()->for($this->gallery)->create(['name' => 'Trauung', 'position' => 1]);

    $this->other = Gallery::factory()->for(Project::factory())->create();
    $this->otherSet = GallerySet::where('gallery_id', $this->other->id)->first();
    $this->otherAsset = Asset::factory()->create([
        'gallery_id' => $this->other->id,
        'gallery_set_id' => $this->otherSet->id,
        'key' => 'galleries/other/a.jpg',
    ]);
});

// Entscheidung 50. Ohne scopeBindings() reicht eine manageMedia-Berechtigung
// an irgendeiner Galerie über den eigenen Pfad an jedes fremde Set und Bild.
// Dieser Test ist die einzige Stelle, an der auffällt, wenn das Scoping
// wieder abfällt.
it('refuses a foreign set under this gallery', function () {
    // Dieselbe Antwort wie auf ein gelöschtes Set: die Umleitung sagt nicht,
    // ob es das fremde überhaupt gibt. Entscheidend ist, dass seine Bilder
    // nicht erscheinen.
    $this->get("/galerien/{$this->gallery->public_id}/bilder/{$this->otherSet->public_id}")
        ->assertRedirect("/galerien/{$this->gallery->public_id}/bilder");

    $props = $this->get("/galerien/{$this->gallery->public_id}/bilder")->viewData('page')['props'];

    expect($props['activeSetId'])->toBeNull()
        ->and(collect($props['sets'])->pluck('id'))->not->toContain($this->otherSet->public_id)
        ->and(collect($props['assets'])->pluck('id'))->not->toContain($this->otherAsset->public_id);
});

it('refuses a foreign asset under this gallery', function () {
    $this->get("/galerien/{$this->gallery->public_id}/bilder/{$this->otherAsset->public_id}/original")
        ->assertNotFound();

    $this->get("/galerien/{$this->gallery->public_id}/bilder/{$this->otherAsset->public_id}/vorschau")
        ->assertNotFound();
});

it('refuses to rename a foreign set through this gallery', function () {
    $this->patch("/galerien/{$this->gallery->public_id}/sets/{$this->otherSet->public_id}", ['name' => 'Gekapert'])
        ->assertNotFound();

    expect($this->otherSet->fresh()->name)->not->toBe('Gekapert');
});

// Entscheidung 51. Ein Set zusammenzulegen ist Aufräumen, kein Grund, alte
// Lesezeichen zu vergiften.
it('sends a bookmark on a deleted set back to the pictures', function () {
    $id = $this->set->public_id;
    $this->set->delete();

    $this->get("/galerien/{$this->gallery->public_id}/bilder/{$id}")
        ->assertRedirect("/galerien/{$this->gallery->public_id}/bilder");
});

it('sends an unknown settings section to allgemein', function () {
    $this->get("/galerien/{$this->gallery->public_id}/einstellungen/farbe")
        ->assertRedirect("/galerien/{$this->gallery->public_id}/einstellungen/allgemein");
});

it('still answers 404 for a gallery that does not exist', function () {
    $this->get("/galerien/gibtesnicht/bilder/{$this->set->public_id}")->assertNotFound();
});

// Entscheidung 45: Bereich, Set und Abschnitt stehen im Pfad und kommen als
// Props zurück — sonst wüsste die Seite nach dem Neuladen nicht, wo sie ist.
it('carries area, set and section as props', function () {
    $props = fn (string $url) => $this->get($url)->viewData('page')['props'];

    expect($props("/galerien/{$this->gallery->public_id}"))
        ->toMatchArray(['area' => 'bilder', 'activeSetId' => null, 'settingsSection' => 'allgemein']);

    expect($props("/galerien/{$this->gallery->public_id}/bilder/{$this->set->public_id}"))
        ->toMatchArray(['area' => 'bilder', 'activeSetId' => $this->set->public_id]);

    expect($props("/galerien/{$this->gallery->public_id}/einstellungen/zugang"))
        ->toMatchArray(['area' => 'einstellungen', 'settingsSection' => 'zugang']);

    expect($props("/galerien/{$this->gallery->public_id}/aktivitaet"))
        ->toMatchArray(['area' => 'aktivitaet']);
});

// Entscheidung 53: die teuren Blätter reisen nicht mit, wenn ihr Bereich zu
// ist. Ein Test darauf, weil es sonst still zurückfällt.
it('leaves the other areas out of the payload', function () {
    $props = $this->get("/galerien/{$this->gallery->public_id}/bilder")->viewData('page')['props'];

    expect($props)->toHaveKeys(['sets', 'assets', 'pendingUploads'])
        ->and($props)->not->toHaveKey('activity')
        ->and($props)->not->toHaveKey('builds')
        ->and($props)->not->toHaveKey('lists');
});
