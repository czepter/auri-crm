<?php

use App\Crm\Models\Project;
use App\Gallery\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withMfa()->create(), 'staff');
});

it('creates a gallery and sends the user to the ingest screen', function () {
    $project = Project::factory()->create();

    $response = $this->post('/galerien', [
        'project_id' => $project->public_id,
        'title' => 'Trauung',
        'is_external' => false,
        'expires_at' => '2027-01-31',
        'downloads_enabled' => true,
        'lists_enabled' => false,
        'comments_enabled' => true,
    ]);

    $gallery = Gallery::sole();

    $response->assertRedirect("/galerien/{$gallery->public_id}/medien");

    expect($gallery->title)->toBe('Trauung')
        ->and($gallery->project_id)->toBe($project->id)
        ->and($gallery->lists_enabled)->toBeFalse()
        ->and($gallery->expires_at->toDateString())->toBe('2027-01-31');
});

it('creates one without a project', function () {
    $this->post('/galerien', [
        'project_id' => null,
        'title' => 'Studioportraits',
        'is_external' => false,
        'downloads_enabled' => true,
        'lists_enabled' => true,
        'comments_enabled' => true,
    ])->assertRedirect();

    expect(Gallery::sole()->project_id)->toBeNull();
});

// Eine externe Galerie hostet nichts: publish() wirft bei ihr, und es gibt
// keinen Ingest-Screen, auf den man landen könnte.
it('sends an external gallery to its record instead', function () {
    $response = $this->post('/galerien', [
        'project_id' => null,
        'title' => 'Pixieset 2024',
        'is_external' => true,
        'external_url' => 'https://example.test/g/abc',
    ]);

    $gallery = Gallery::sole();

    $response->assertRedirect("/galerien/{$gallery->public_id}");

    expect($gallery->is_external)->toBeTrue()
        ->and($gallery->external_url)->toBe('https://example.test/g/abc');
});

it('refuses an external gallery without a url', function () {
    $this->post('/galerien', [
        'project_id' => null,
        'title' => 'Kaputt',
        'is_external' => true,
    ])->assertSessionHasErrors('external_url');
});

// Die Zuordnung laeuft ueber visibleTo, wie der Index. Eine Rolle mit
// ManageShoots ohne ViewAllProjects gibt es in StaffRole nicht, also hat der
// Sichtbarkeitszweig kein Subjekt — pruefbar bleibt, dass eine Id, die diese
// Abfrage nicht findet, ein Fehler ist und keine projektlose Galerie.
it('refuses an unknown project id instead of dropping the project', function () {
    $this->post('/galerien', [
        'project_id' => 'nicht-vorhanden',
        'title' => 'Tippfehler',
        'is_external' => false,
    ])->assertSessionHasErrors('project_id');

    expect(Gallery::count())->toBe(0);
});
