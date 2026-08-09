<?php

use App\Crm\Actions\CheckDateAvailability;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function bookedOn(string $date, string $name = 'Meier & Kowalski'): Project
{
    $project = Project::factory()->wedding()->create(['name' => $name]);
    Lead::factory()->for($project)->create();
    Shoot::factory()->for($project)->primary()->on($date)->create([
        'venue_name' => 'Gut Basthorst',
        'venue_city' => 'Basthorst',
    ]);

    return $project;
}

it('reports nothing for a free date', function () {
    bookedOn('2026-06-14');

    expect(app(CheckDateAvailability::class)->forDate('2026-06-20'))->toBe([]);
});

it('reports the colliding project with its name and venue', function () {
    bookedOn('2026-06-14');

    $conflicts = app(CheckDateAvailability::class)->forDate('2026-06-14');

    expect($conflicts)->toHaveCount(1)
        ->and($conflicts[0]->projectName)->toBe('Meier & Kowalski')
        ->and($conflicts[0]->venue)->toBe('Gut Basthorst, Basthorst')
        ->and($conflicts[0]->projectId)->toHaveLength(24);
});

it('ignores lost leads', function () {
    // Vision §7: the scan covers projects whose lead is not lost.
    $project = bookedOn('2026-06-14');
    $project->lead->forceFill(['lost_at' => now(), 'lost_reason' => 'Budget'])->save();

    expect(app(CheckDateAvailability::class)->forDate('2026-06-14'))->toBe([]);
});

it('reports nothing when there is no date', function () {
    bookedOn('2026-06-14');

    expect(app(CheckDateAvailability::class)->forDate(null))->toBe([]);
});

it('excludes the project being checked', function () {
    $project = bookedOn('2026-06-14');

    expect(app(CheckDateAvailability::class)->forDate('2026-06-14', $project))->toBe([]);
});

it('does not query once per date', function () {
    bookedOn('2026-06-14');
    bookedOn('2026-08-22', 'Vehse & Rieck');

    DB::enableQueryLog();
    $result = app(CheckDateAvailability::class)->forDates([
        '2026-06-14', '2026-08-22', '2026-09-05', '2026-03-14',
    ]);
    $queries = DB::getRawQueryLog();
    DB::disableQueryLog();

    // Two, regardless of how many dates: the shoots query plus the eager load
    // of their projects. Four dates must not mean four lookups.
    expect($queries)->toHaveCount(2)
        ->and($result['2026-06-14'])->toHaveCount(1)
        ->and($result['2026-08-22'])->toHaveCount(1)
        ->and($result)->not->toHaveKey('2026-09-05');
});

it('serves the availability endpoint to a signed-in staff user', function () {
    bookedOn('2026-06-14');
    $user = User::factory()->withMfa()->create();

    $this->actingAs($user, 'staff')
        ->getJson('/verfuegbarkeit?datum=2026-06-14')
        ->assertOk()
        ->assertJsonCount(1, 'conflicts')
        ->assertJsonPath('conflicts.0.projectName', 'Meier & Kowalski');
});

it('rejects an unauthenticated availability check', function () {
    $this->get('/verfuegbarkeit?datum=2026-06-14')->assertRedirect('/login');
});
