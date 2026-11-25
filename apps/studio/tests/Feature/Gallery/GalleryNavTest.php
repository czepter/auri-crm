<?php

use App\Crm\Models\ClientCase;
use App\Gallery\Models\AssetComment;
use App\Gallery\Models\AssetFavorite;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withMfa()->create(), 'staff');
    $this->gallery = Gallery::factory()->for(ClientCase::factory(), 'case')->create();
    $this->highlights = GallerySet::where('gallery_id', $this->gallery->id)->first();
});

function nav(Gallery $gallery): array
{
    return test()->get("/galerien/{$gallery->public_id}/bilder")->viewData('page')['props']['nav'];
}

function tab(array $nav, string $key): array
{
    return collect($nav['tabs'])->firstWhere('key', $key);
}

function picture(Gallery $gallery, GallerySet $set, string $filename): Asset
{
    return Asset::factory()->create([
        'gallery_id' => $gallery->id,
        'gallery_set_id' => $set->id,
        'filename' => $filename,
        'key' => "galleries/{$gallery->public_id}/{$filename}",
    ]);
}

// Entscheidung 55: die ehrliche Antwort vor der ersten Veröffentlichung ist
// keine Null, sondern gar nichts.
it('leaves the pictures counter empty on a draft', function () {
    picture($this->gallery, $this->highlights, 'a.jpg');

    expect(tab(nav($this->gallery), 'bilder'))
        ->toMatchArray(['count' => null, 'countTo' => null]);
});

// Entscheidung 54.
it('counts the published version, not the draft', function () {
    picture($this->gallery, $this->highlights, 'a.jpg');
    $this->gallery->publish();

    expect(tab(nav($this->gallery->fresh()), 'bilder'))->toMatchArray(['count' => 1, 'countTo' => null]);
});

// Entscheidung 57: ohne das ist ein nicht veröffentlichter Upload in der
// Navigation unsichtbar.
it('shows the draft beside the version once they part', function () {
    picture($this->gallery, $this->highlights, 'a.jpg');
    $this->gallery->publish();
    picture($this->gallery, $this->highlights, 'b.jpg');

    expect(tab(nav($this->gallery->fresh()), 'bilder'))->toMatchArray(['count' => 1, 'countTo' => 2]);
});

// Entscheidung 44.
it('hangs the sets under the pictures and hides Alle until the second', function () {
    picture($this->gallery, $this->highlights, 'a.jpg');

    $children = tab(nav($this->gallery), 'bilder')['children'];
    expect($children)->toHaveCount(1)
        ->and($children[0])->toMatchArray(['label' => $this->highlights->name, 'translate' => false, 'count' => null]);

    $trauung = GallerySet::factory()->for($this->gallery)->create(['name' => 'Trauung', 'position' => 1]);
    picture($this->gallery, $trauung, 'b.jpg');

    $children = tab(nav($this->gallery), 'bilder')['children'];
    expect($children)->toHaveCount(3)
        ->and($children[0])->toMatchArray(['key' => 'alle', 'translate' => true, 'count' => null, 'reorderable' => false]);
});

// Entscheidung 58: Markierungen und Kommentare, keine Aufrufe — ein Zähler
// taugt nur für eine Menge, die man abarbeiten kann.
it('counts marks and comments on activity, and nothing else', function () {
    $asset = picture($this->gallery, $this->highlights, 'a.jpg');
    $this->gallery->publish();
    $version = $this->gallery->fresh()->currentVersion;

    AssetFavorite::create(['gallery_version_id' => $version->id, 'asset_key' => $asset->key, 'guest_identifier' => 'g1']);
    AssetComment::create(['gallery_version_id' => $version->id, 'asset_key' => $asset->key, 'guest_identifier' => 'g1', 'body' => 'schön']);

    expect(tab(nav($this->gallery->fresh()), 'aktivitaet')['count'])->toBe(2);
});

// Entscheidung 40: das Titelbild steht über den Einträgen, in jedem Bereich.
it('carries the cover in every area', function () {
    $asset = picture($this->gallery, $this->highlights, 'a.jpg');
    $this->gallery->update(['cover_asset_id' => $asset->id]);

    foreach (['bilder', 'gestaltung', 'einstellungen/zugang', 'aktivitaet'] as $area) {
        $props = $this->get("/galerien/{$this->gallery->public_id}/{$area}")->viewData('page')['props'];

        expect($props['nav']['cover'])->toContain($asset->public_id);
    }
});
