<?php

use App\Crm\Actions\AdvanceShootStatus;
use App\Crm\Actions\CheckDateAvailability;
use App\Crm\Enums\LeadOutcome;
use App\Crm\Enums\ShootStatus;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Lead;
use App\Crm\Models\Shoot;
use App\Models\Absence;
use App\Models\User;
use App\Settings\Models\StudioClosure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function bookedOn(string $date, string $name = 'Meier & Kowalski'): ClientCase
{
    $project = ClientCase::factory()->wedding()->create(['name' => $name]);
    Lead::factory()->for($project, 'case')->create();
    Shoot::factory()->for($project, 'case')->primary()->on($date)->create([
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
        ->and($conflicts[0]->source)->toBe('shoot')
        ->and($conflicts[0]->projectName)->toBe('Meier & Kowalski')
        ->and($conflicts[0]->venue)->toBe('Gut Basthorst, Basthorst')
        ->and($conflicts[0]->projectId)->toHaveLength(24);
});

it('reports a date inside a studio closure, naming the closure', function () {
    StudioClosure::factory()->spanning('2026-12-24', '2026-12-31')->create(['label' => 'Weihnachtspause']);

    $conflicts = app(CheckDateAvailability::class)->forDate('2026-12-26');

    expect($conflicts)->toHaveCount(1)
        ->and($conflicts[0]->source)->toBe('closure')
        ->and($conflicts[0]->label)->toBe('Weihnachtspause')
        ->and($conflicts[0]->projectId)->toBeNull();
});

it('reports nothing for a date outside every closure range', function () {
    StudioClosure::factory()->spanning('2026-12-24', '2026-12-31')->create();

    expect(app(CheckDateAvailability::class)->forDate('2026-12-23'))->toBe([])
        ->and(app(CheckDateAvailability::class)->forDate('2027-01-01'))->toBe([]);
});

it('reports both a shoot and a closure conflict on the same date, each naming its source', function () {
    bookedOn('2026-12-26');
    StudioClosure::factory()->spanning('2026-12-24', '2026-12-31')->create(['label' => 'Weihnachtspause']);

    $conflicts = app(CheckDateAvailability::class)->forDate('2026-12-26');
    $sources = collect($conflicts)->pluck('source')->sort()->values()->all();

    expect($conflicts)->toHaveCount(2)
        ->and($sources)->toBe(['closure', 'shoot']);
});

it('reports a date inside a person\'s absence, naming them and the category', function () {
    Absence::factory()->for(User::factory()->create(['name' => 'Nina Sturm']))
        ->spanning('2026-09-01', '2026-09-07')
        ->create(['category' => 'holiday']);

    $conflicts = app(CheckDateAvailability::class)->forDate('2026-09-03');

    expect($conflicts)->toHaveCount(1)
        ->and($conflicts[0]->source)->toBe('absence')
        ->and($conflicts[0]->label)->toBe('Nina Sturm · Urlaub')
        ->and($conflicts[0]->projectId)->toBeNull()
        ->and($conflicts[0]->personId)->toHaveLength(24);
});

it('reports nothing for a date outside every absence range', function () {
    Absence::factory()->spanning('2026-09-01', '2026-09-07')->create();

    expect(app(CheckDateAvailability::class)->forDate('2026-08-31'))->toBe([])
        ->and(app(CheckDateAvailability::class)->forDate('2026-09-08'))->toBe([]);
});

it('reports a shoot, a closure and an absence on the same date, each naming its source', function () {
    bookedOn('2026-12-26');
    StudioClosure::factory()->spanning('2026-12-24', '2026-12-31')->create(['label' => 'Weihnachtspause']);
    Absence::factory()->spanning('2026-12-24', '2026-12-31')->create();

    $conflicts = app(CheckDateAvailability::class)->forDate('2026-12-26');
    $sources = collect($conflicts)->pluck('source')->sort()->values()->all();

    expect($conflicts)->toHaveCount(3)
        ->and($sources)->toBe(['absence', 'closure', 'shoot']);
});

it('respects closures across every date passed to forDates', function () {
    StudioClosure::factory()->spanning('2026-12-24', '2026-12-31')->create(['label' => 'Weihnachtspause']);
    StudioClosure::factory()->spanning('2026-03-01', '2026-03-03')->create(['label' => 'Umbau']);

    $result = app(CheckDateAvailability::class)->forDates([
        '2026-12-26', '2026-03-02', '2026-06-01',
    ]);

    expect($result['2026-12-26'])->toHaveCount(1)
        ->and($result['2026-12-26'][0]->label)->toBe('Weihnachtspause')
        ->and($result['2026-03-02'][0]->label)->toBe('Umbau')
        ->and($result)->not->toHaveKey('2026-06-01');
});

it('ignores lost leads', function () {
    // Vision §7: the scan covers projects whose lead is not lost.
    $project = bookedOn('2026-06-14');
    $project->forceFill(['closed_at' => now(), 'close_reason' => 'Budget', 'close_outcome' => LeadOutcome::Lost])->save();

    expect(app(CheckDateAvailability::class)->forDate('2026-06-14'))->toBe([]);
});

it('ignores cancelled shoots', function () {
    $project = bookedOn('2026-06-14');
    app(AdvanceShootStatus::class)->cancel($project->shoots()->sole(), 'Verschoben');

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
    StudioClosure::factory()->spanning('2026-12-24', '2026-12-31')->create();

    // The stage table is read once per request and memoised (CrmVocabulary);
    // warming it here keeps that one-off read out of the count, which is
    // about growth per date rather than about fixed lookups.
    stage(ShootStatus::Booked);

    DB::enableQueryLog();
    $result = app(CheckDateAvailability::class)->forDates([
        '2026-06-14', '2026-08-22', '2026-09-05', '2026-03-14',
    ]);
    $queries = DB::getRawQueryLog();
    DB::disableQueryLog();

    // Four, regardless of how many dates: the shoots query, the eager load
    // of their projects, one closures query and one absences query for the
    // whole range. Four dates must not mean four lookups, and a third
    // source must not turn this into a fifth or sixth.
    expect($queries)->toHaveCount(4)
        ->and($result['2026-06-14'])->toHaveCount(1)
        ->and($result['2026-08-22'])->toHaveCount(1)
        ->and($result)->not->toHaveKey('2026-09-05');
});

it('runs the same fixed number of queries whether or not any closure is in range', function () {
    bookedOn('2026-06-14');
    // The stage table is read once per request and memoised (CrmVocabulary);
    // warming it here keeps that one-off read out of the count, which is
    // about growth per date rather than about fixed lookups.
    stage(ShootStatus::Booked);

    DB::enableQueryLog();
    app(CheckDateAvailability::class)->forDates(['2026-06-14', '2026-08-22', '2026-09-05', '2026-03-14']);
    $withoutClosures = count(DB::getRawQueryLog());
    DB::flushQueryLog();
    DB::disableQueryLog();

    StudioClosure::factory()->spanning('2026-12-24', '2026-12-31')->create();

    DB::enableQueryLog();
    app(CheckDateAvailability::class)->forDates(['2026-06-14', '2026-08-22', '2026-09-05', '2026-03-14']);
    $withClosures = count(DB::getRawQueryLog());
    DB::disableQueryLog();

    expect($withClosures)->toBe($withoutClosures);
});

it('runs the same fixed number of queries whether or not any absence is in range', function () {
    bookedOn('2026-06-14');
    // The stage table is read once per request and memoised (CrmVocabulary);
    // warming it here keeps that one-off read out of the count, which is
    // about growth per date rather than about fixed lookups.
    stage(ShootStatus::Booked);

    DB::enableQueryLog();
    app(CheckDateAvailability::class)->forDates(['2026-06-14', '2026-08-22', '2026-09-05', '2026-03-14']);
    $withoutAbsences = count(DB::getRawQueryLog());
    DB::flushQueryLog();
    DB::disableQueryLog();

    Absence::factory()->spanning('2026-12-24', '2026-12-31')->create();

    DB::enableQueryLog();
    app(CheckDateAvailability::class)->forDates(['2026-06-14', '2026-08-22', '2026-09-05', '2026-03-14']);
    $withAbsences = count(DB::getRawQueryLog());
    DB::disableQueryLog();

    expect($withAbsences)->toBe($withoutAbsences);
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

it('serves a closure conflict through the same endpoint, naming the closure', function () {
    StudioClosure::factory()->spanning('2026-12-24', '2026-12-31')->create(['label' => 'Weihnachtspause']);
    $user = User::factory()->withMfa()->create();

    $this->actingAs($user, 'staff')
        ->getJson('/verfuegbarkeit?datum=2026-12-26')
        ->assertOk()
        ->assertJsonCount(1, 'conflicts')
        ->assertJsonPath('conflicts.0.source', 'closure')
        ->assertJsonPath('conflicts.0.label', 'Weihnachtspause');
});
