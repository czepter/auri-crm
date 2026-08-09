<?php

use App\Crm\Models\InternalNote;
use App\Crm\Models\Project;
use App\Models\User;
use App\Support\Surface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// routesForSurface() lives in tests/Pest.php — see the note there.

it('belongs to a project and names its author', function () {
    $project = Project::factory()->create();
    $author = User::factory()->create();

    $note = InternalNote::factory()->for($project)
        ->create(['author_id' => $author->id, 'body' => 'Gegenlicht ab 20 Uhr.']);

    expect($note->project->is($project))->toBeTrue()
        ->and($note->author->is($author))->toBeTrue()
        ->and($note->body)->toBe('Gegenlicht ab 20 Uhr.');
});

it('is editable and deletable by any staff member', function () {
    // The audience is the whole studio, and audit_logs already records who
    // changed what — so there is no per-author ownership rule to enforce.
    $note = InternalNote::factory()->create();

    $note->update(['body' => 'Korrigiert.']);
    expect($note->fresh()->body)->toBe('Korrigiert.');

    $note->delete();
    expect(InternalNote::count())->toBe(0);
});

it('lives in its own table rather than as a flag on a shared one', function () {
    expect(Schema::hasTable('internal_notes'))->toBeTrue()
        ->and((new InternalNote)->getTable())->toBe('internal_notes');
});

/**
 * The structural guarantee C-07 rests on. The portal surface does not exist
 * yet; this test is written now so that the day routes/portal.php lands, the
 * first route that can reach an internal note fails here rather than in a
 * client's browser.
 */
it('is unreachable from every surface that is not the studio', function (string $surface) {
    $reaching = collect(routesForSurface($surface))
        ->map(fn ($route) => $route->getAction('controller'))
        ->filter()
        ->map(fn (string $action) => Str::before($action, '@'))
        ->unique()
        ->filter(fn (string $class) => class_exists($class)
            && str_contains((string) file_get_contents((new ReflectionClass($class))->getFileName()), 'InternalNote'))
        ->values()
        ->all();

    expect($reaching)->toBe([]);
})->with(array_values(array_diff(Surface::ALL, [Surface::ADMIN])));
