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
 * The structural guarantee C-07 rests on: no client or guest ever reaches an
 * internal note. This test is written before routes/portal.php carries any
 * real routes, so the first one that can reach a note fails here rather than
 * in a client's browser.
 *
 * The api surface is deliberately not checked here any more — I-03 gives it
 * its own scope-gated internal-note route (App\Integration\Http\Controllers\
 * InternalNoteController), reached only by an ApiToken holding manage_notes,
 * the same capability a staff role needs. That is machine access to ordinary
 * studio work, not the client leak this test exists to catch —
 * tests/Feature/Integration/ApiInternalNoteWriteTest.php proves the scope
 * gate on that route instead.
 */
it('is unreachable from the portal surface', function () {
    $reaching = collect(routesForSurface(Surface::PORTAL))
        ->map(fn ($route) => $route->getAction('controller'))
        ->filter()
        ->map(fn (string $action) => Str::before($action, '@'))
        ->unique()
        ->filter(fn (string $class) => class_exists($class)
            && str_contains((string) file_get_contents((new ReflectionClass($class))->getFileName()), 'InternalNote'))
        ->values()
        ->all();

    expect($reaching)->toBe([]);
});
