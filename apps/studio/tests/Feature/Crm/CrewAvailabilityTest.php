<?php

use App\Crm\Actions\CrewAvailability;
use App\Crm\Data\CrewCandidateData;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Enums\StaffRole;
use App\Models\Absence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @param  list<CrewCandidateData>  $candidates */
function candidate(array $candidates, User $user): CrewCandidateData
{
    return collect($candidates)->firstOrFail(fn (CrewCandidateData $one) => $one->id === $user->public_id);
}

it('answers free, absent or busy for every candidate on the shooting day', function () {
    $free = User::factory()->withMfa()->create(['role' => StaffRole::Manager, 'name' => 'Ada Frei']);
    $away = User::factory()->withMfa()->create(['role' => StaffRole::Contributor, 'name' => 'Nina Sturm']);
    $busy = User::factory()->withMfa()->create(['role' => StaffRole::Contributor, 'name' => 'Tom Boll']);

    Absence::factory()->for($away)->spanning('2026-09-01', '2026-09-07')->create(['category' => 'holiday']);

    $project = Project::factory()->create();
    $shoot = Shoot::factory()->for($project)->on('2026-09-03')->create();

    $other = Project::factory()->create(['name' => 'Sommer & Winter']);
    $otherShoot = Shoot::factory()->for($other)->on('2026-09-03')->create();
    CrewAssignment::factory()->for($otherShoot)->for($busy)->create();

    $candidates = app(CrewAvailability::class)->forProject($project)[$shoot->public_id];

    expect(candidate($candidates, $free)->status)->toBe('free')
        ->and(candidate($candidates, $free)->note)->toBeNull()
        ->and(candidate($candidates, $away)->status)->toBe('absent')
        ->and(candidate($candidates, $away)->note)->toBe('Urlaub')
        ->and(candidate($candidates, $busy)->status)->toBe('busy')
        ->and(candidate($candidates, $busy)->note)->toBe('Sommer & Winter');
});

it('does not call somebody busy for the very shoot they are being picked for', function () {
    $tom = staffMember(StaffRole::Contributor);

    $project = Project::factory()->create();
    $shoot = Shoot::factory()->for($project)->on('2026-09-03')->create();
    CrewAssignment::factory()->for($shoot)->for($tom)->create();

    $candidates = app(CrewAvailability::class)->forProject($project)[$shoot->public_id];

    expect(candidate($candidates, $tom)->status)->toBe('free');
});

it('leaves everybody free on a shoot that has no date yet', function () {
    $nina = staffMember(StaffRole::Contributor);
    Absence::factory()->for($nina)->spanning('2026-09-01', '2026-09-07')->create();

    $project = Project::factory()->create();
    $shoot = Shoot::factory()->for($project)->create(['date' => null]);

    $candidates = app(CrewAvailability::class)->forProject($project)[$shoot->public_id];

    expect(candidate($candidates, $nina)->status)->toBe('free');
});
