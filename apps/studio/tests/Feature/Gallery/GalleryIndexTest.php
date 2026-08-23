<?php

use App\Crm\Models\Project;
use App\Gallery\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

it('lists a gallery with its booking, its count and its version', function () {
    $project = Project::factory()->create(['name' => 'Basthorst 2026']);
    $gallery = Gallery::factory()->for($project)->create([
        'title' => 'Trauung',
        'assets' => [['key' => 'galleries/g1/a.jpg', 'width' => 200, 'height' => 100]],
    ]);
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
// ViewAllProjects gibt es in StaffRole nicht, also hat die Gegenprobe kein
// Subjekt.
it('shows a projectless gallery to every role that may manage media', function () {
    $orphan = Gallery::factory()->create(['project_id' => null]);

    expect(collect(props('/galerien')['galleries'])->pluck('id'))
        ->toContain($orphan->public_id);
});

// Der Sichtbarkeitszweig der Abfrage. Keine Rolle traegt ManageShoots ohne
// ViewAllProjects, also ist der Crew-Pfad von visibleTo hier nicht erreichbar;
// pruefbar bleibt, dass eine Galerie mit Buchung ueberhaupt durch ihn kommt.
it('carries a gallery whose booking this role may see', function () {
    $gallery = Gallery::factory()->for(Project::factory())->create();

    expect(collect(props('/galerien')['galleries'])->pluck('id'))
        ->toContain($gallery->public_id);
});
