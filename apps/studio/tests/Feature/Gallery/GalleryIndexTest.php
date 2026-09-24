<?php

use App\Crm\Models\ClientCase;
use App\Gallery\Models\Gallery;
use App\Media\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

it('lists a gallery with its booking, its count and its version', function () {
    $project = ClientCase::factory()->create(['name' => 'Basthorst 2026']);
    $gallery = Gallery::factory()->for($project, 'case')->create(['title' => 'Trauung']);
    Asset::factory()->create(['gallery_id' => $gallery->id, 'width' => 200, 'height' => 100]);
    $gallery->publish();

    $row = collect(props('/galerien')['galleries'])->firstWhere('id', $gallery->public_id);

    expect($row['title'])->toBe('Trauung')
        ->and($row['projectName'])->toBe('Basthorst 2026')
        ->and($row['assetCount'])->toBe(1)
        ->and($row['version'])->toBe(1);
});

it('puts the newest gallery first', function () {
    $older = Gallery::factory()->create(['created_at' => now()->subDay()]);
    $newer = Gallery::factory()->create();

    $ids = collect(props('/galerien')['galleries'])->pluck('id')->all();

    expect(array_search($newer->public_id, $ids, true))
        ->toBeLessThan(array_search($older->public_id, $ids, true));
});

// Die eine Ausnahme von der Projektsichtbarkeit, ausdrücklich: es gibt nichts,
// woran ein Filter greifen könnte. Eine Rolle mit ManageShoots ohne
// ViewAllCases gibt es in StaffRole nicht, also hat die Gegenprobe kein
// Subjekt.
it('shows a projectless gallery to every role that may manage media', function () {
    $orphan = Gallery::factory()->create(['case_id' => null]);

    expect(collect(props('/galerien')['galleries'])->pluck('id'))
        ->toContain($orphan->public_id);
});

// Der Sichtbarkeitszweig der Abfrage. Keine Rolle traegt ManageShoots ohne
// ViewAllCases, also ist der Crew-Pfad von visibleTo hier nicht erreichbar;
// pruefbar bleibt, dass eine Galerie mit Buchung ueberhaupt durch ihn kommt.
it('carries a gallery whose booking this role may see', function () {
    $gallery = Gallery::factory()->for(ClientCase::factory(), 'case')->create();

    expect(collect(props('/galerien')['galleries'])->pluck('id'))
        ->toContain($gallery->public_id);
});
