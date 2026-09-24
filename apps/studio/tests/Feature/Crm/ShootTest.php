<?php

use App\Crm\Enums\ShootStatus;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Shoot;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('starts as requested', function () {
    expect(Shoot::factory()->create()->status->key)->toBe(ShootStatus::Requested->value);
});

it('stores the date as a date and the times as times', function () {
    // A shooting day is a calendar day and must not shift with a timezone,
    // so this is a date column and never a timestamp (vision §8).
    $shoot = Shoot::factory()->create([
        'date' => '2026-06-14',
        'call_time' => '13:30',
        'end_time' => '23:00',
    ]);

    $raw = DB::table('shoots')->where('id', $shoot->id)->sole();

    expect($raw->date)->toBe('2026-06-14')
        ->and($shoot->fresh()->date->format('d.m.Y'))->toBe('14.06.2026')
        ->and($raw->call_time)->toStartWith('13:30');
});

it('allows a shoot with no date yet', function () {
    // "Termin offen" on the board. A required date would force staff to invent
    // one, corrupting the column the availability check reads.
    expect(Shoot::factory()->create(['date' => null])->date)->toBeNull();
});

it('refuses a second primary shoot on one project', function () {
    $project = ClientCase::factory()->create();

    Shoot::factory()->for($project, 'case')->primary()->create();
    Shoot::factory()->for($project, 'case')->primary()->create();
})->throws(QueryException::class);

it('allows any number of non-primary shoots on one project', function () {
    $project = ClientCase::factory()->create();

    Shoot::factory()->for($project, 'case')->primary()->create();
    Shoot::factory()->for($project, 'case')->count(3)->create();

    expect($project->shoots()->count())->toBe(4)
        ->and($project->primaryShoot->is_primary)->toBeTrue();
});

it('renders a venue only when it has one', function () {
    $withVenue = Shoot::factory()->create([
        'venue_name' => 'Gut Basthorst',
        'venue_city' => 'Basthorst',
    ]);
    $partial = Shoot::factory()->create(['venue_name' => 'Studio', 'venue_city' => null]);
    $without = Shoot::factory()->create(['venue_name' => null, 'venue_city' => null]);

    expect($withVenue->venue())->toBe('Gut Basthorst, Basthorst')
        ->and($partial->venue())->toBe('Studio')
        ->and($without->venue())->toBeNull();
});
