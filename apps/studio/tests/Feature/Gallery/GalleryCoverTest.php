<?php

use App\Crm\Models\ClientCase;
use App\Gallery\Actions\EnsureGalleryCover;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');

    $this->gallery = Gallery::factory()->for(ClientCase::factory(), 'case')->create();
    $this->highlights = GallerySet::where('gallery_id', $this->gallery->id)->first();
});

function put(Gallery $gallery, GallerySet $set, string $filename): Asset
{
    return Asset::factory()->create([
        'gallery_id' => $gallery->id,
        'gallery_set_id' => $set->id,
        'filename' => $filename,
        'key' => "galleries/{$gallery->public_id}/{$filename}",
    ]);
}

// Entscheidung 59: alphabetisch erstes im ersten Set — nicht das erste, das
// den Ingest schafft. Parallele Uploads werden in beliebiger Reihenfolge
// fertig.
it('takes the first picture by name in the first set', function () {
    $trauung = GallerySet::factory()->for($this->gallery)->create(['name' => 'Trauung', 'position' => 1]);

    put($this->gallery, $trauung, 'aaa.jpg');
    $expected = put($this->gallery, $this->highlights, 'zzz.jpg');

    (new EnsureGalleryCover)($this->gallery);

    expect($this->gallery->fresh()->cover_asset_id)->toBe($expected->id);
});

// Entscheidung 60: der naechste Stapel kassiert die Wahl des Menschen nicht.
it('leaves a chosen cover alone', function () {
    $chosen = put($this->gallery, $this->highlights, 'm.jpg');
    $this->gallery->update(['cover_asset_id' => $chosen->id]);

    put($this->gallery, $this->highlights, 'a.jpg');
    (new EnsureGalleryCover)($this->gallery->fresh());

    expect($this->gallery->fresh()->cover_asset_id)->toBe($chosen->id);
});

it('sets nothing when the gallery is empty', function () {
    (new EnsureGalleryCover)($this->gallery);

    expect($this->gallery->fresh()->cover_asset_id)->toBeNull();
});

// Entscheidung 61: geloescht heisst nachruecken, nicht leer.
it('promotes the next picture when the cover is deleted', function () {
    $cover = put($this->gallery, $this->highlights, 'a.jpg');
    $next = put($this->gallery, $this->highlights, 'b.jpg');
    $this->gallery->update(['cover_asset_id' => $cover->id]);

    $this->delete("/galerien/{$this->gallery->public_id}/bilder", ['asset_ids' => [$cover->public_id]])
        ->assertRedirect();

    expect($this->gallery->fresh()->cover_asset_id)->toBe($next->id);
});

it('promotes the next picture when the set holding the cover goes', function () {
    $feier = GallerySet::factory()->for($this->gallery)->create(['name' => 'Feier', 'position' => 1]);
    $cover = put($this->gallery, $feier, 'a.jpg');
    $survivor = put($this->gallery, $this->highlights, 'b.jpg');
    $this->gallery->update(['cover_asset_id' => $cover->id]);

    $this->delete("/galerien/{$this->gallery->public_id}/sets/{$feier->public_id}")->assertRedirect();

    expect($this->gallery->fresh()->cover_asset_id)->toBe($survivor->id);
});
