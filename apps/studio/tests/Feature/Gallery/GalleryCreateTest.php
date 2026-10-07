<?php

use App\Crm\Models\ClientCase;
use App\Crm\Models\Shoot;
use App\Gallery\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->withMfa()->create(), 'staff');
});

it('opens the mode named in the url, internal otherwise', function () {
    expect(props('/galerien/neu?art=extern')['mode'])->toBe('external')
        ->and(props('/galerien/neu?art=fremd')['mode'])->toBe('internal');
});

it('creates a gallery and sends the user to its record', function () {
    $project = ClientCase::factory()->create();

    $response = $this->post('/galerien', [
        'case_id' => $project->public_id,
        'title' => 'Trauung',
        'is_external' => false,
        'expires_at' => '2027-01-31',
        'downloads_enabled' => true,
        'lists_enabled' => false,
        'comments_enabled' => true,
    ]);

    $gallery = Gallery::sole();

    $response->assertRedirect("/galerien/{$gallery->public_id}");

    expect($gallery->title)->toBe('Trauung')
        ->and($gallery->case_id)->toBe($project->id)
        ->and($gallery->lists_enabled)->toBeFalse()
        ->and($gallery->expires_at->toDateString())->toBe('2027-01-31');
});

it('carries the project and shoot from the project page into the gallery', function () {
    $project = ClientCase::factory()->create();
    $shoot = Shoot::factory()->for($project, 'case')->create();

    $this->get("/galerien/neu?projekt={$project->public_id}&shooting={$shoot->public_id}")
        ->assertInertia(fn ($page) => $page
            ->where('project.id', $project->public_id)
            ->where('shootId', $shoot->public_id));

    $this->post('/galerien', [
        'case_id' => $project->public_id,
        'shoot_id' => $shoot->public_id,
        'title' => 'Hochzeitstag',
        'is_external' => false,
    ]);

    expect(Gallery::sole()->shoot_id)->toBe($shoot->id);
});

it('creates one without a project', function () {
    $this->post('/galerien', [
        'case_id' => null,
        'title' => 'Studioportraits',
        'is_external' => false,
        'downloads_enabled' => true,
        'lists_enabled' => true,
        'comments_enabled' => true,
    ])->assertRedirect();

    expect(Gallery::sole()->case_id)->toBeNull();
});

// Eine externe Galerie hostet nichts: publish() wirft bei ihr, und es gibt
// keinen Ingest-Screen, auf den man landen könnte.
it('sends an external gallery to its record instead', function () {
    $response = $this->post('/galerien', [
        'case_id' => null,
        'title' => 'Pixieset 2024',
        'is_external' => true,
        'external_url' => 'https://example.test/g/abc',
        'info' => 'Passwort per Mail',
        'external_password' => 'geheim',
    ]);

    $gallery = Gallery::sole();

    $response->assertRedirect("/galerien/{$gallery->public_id}");

    expect($gallery->is_external)->toBeTrue()
        ->and($gallery->external_url)->toBe('https://example.test/g/abc')
        ->and($gallery->info)->toBe('Passwort per Mail')
        ->and($gallery->external_password)->toBe('geheim');
});

it('refuses an external gallery without a url', function () {
    $this->post('/galerien', [
        'case_id' => null,
        'title' => 'Kaputt',
        'is_external' => true,
    ])->assertSessionHasErrors('external_url');
});

// Die Zuordnung laeuft ueber visibleTo, wie der Index. Eine Rolle mit
// ManageShoots ohne ViewAllCases gibt es in StaffRole nicht, also hat der
// Sichtbarkeitszweig kein Subjekt — pruefbar bleibt, dass eine Id, die diese
// Abfrage nicht findet, ein Fehler ist und keine projektlose Galerie.
it('refuses an unknown project id instead of dropping the project', function () {
    $this->post('/galerien', [
        'case_id' => 'nicht-vorhanden',
        'title' => 'Tippfehler',
        'is_external' => false,
    ])->assertSessionHasErrors('case_id');

    expect(Gallery::count())->toBe(0);
});
