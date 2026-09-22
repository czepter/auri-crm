<?php

use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Enums\StaffRole;
use App\Models\Absence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * T-10's acceptance criterion, in full: assigning somebody to a shoot that
 * falls inside their own recorded absence warns — through CheckDateAvailability,
 * the same mechanism T-09 already produces its shoot and closure conflicts
 * with — and never blocks the assignment itself.
 */
it('still allows assigning someone during their own recorded absence, but warns on the shoot they are put on', function () {
    $owner = staffMember(StaffRole::Admin);
    $nina = User::factory()->withMfa()->create(['role' => StaffRole::Contributor, 'name' => 'Nina Sturm']);
    Absence::factory()->for($nina)->spanning('2026-09-01', '2026-09-07')->create(['category' => 'holiday']);

    $project = Project::factory()->create();
    $shoot = Shoot::factory()->for($project)->on('2026-09-03')->create();

    $this->actingAs($owner, 'staff')
        ->post("/shootings/{$shoot->public_id}/crew", [
            'user' => $nina->public_id,
            'role' => 'contributor',
        ])
        ->assertRedirect();

    // The assignment happened — a warning is not a block.
    expect(CrewAssignment::where('shoot_id', $shoot->id)->where('user_id', $nina->id)->exists())->toBeTrue();

    $shoots = props("/auftraege/{$project->public_id}/shootings")['shoots'];
    $conflicts = $shoots[0]['crewConflicts'];

    expect($conflicts)->toHaveCount(1)
        ->and($conflicts[0]['source'])->toBe('absence')
        ->and($conflicts[0]['personId'])->toBe($nina->public_id)
        ->and($conflicts[0]['label'])->toBe('Nina Sturm · Urlaub');
});

it('shows no crew conflict for a shoot whose crew has no absence covering its date', function () {
    $owner = staffMember(StaffRole::Admin);
    $nina = staffMember(StaffRole::Contributor);

    $project = Project::factory()->create();
    $shoot = Shoot::factory()->for($project)->on('2026-09-03')->create();
    CrewAssignment::factory()->for($shoot)->for($nina)->create();

    $this->actingAs($owner, 'staff');

    $shoots = props("/auftraege/{$project->public_id}/shootings")['shoots'];

    expect($shoots[0]['crewConflicts'])->toBe([]);
});

it('does not warn about an absence belonging to somebody not crewed on the shoot', function () {
    $owner = staffMember(StaffRole::Admin);
    $mia = staffMember(StaffRole::Manager);
    Absence::factory()->for($mia)->spanning('2026-09-01', '2026-09-07')->create();

    $project = Project::factory()->create();
    $shoot = Shoot::factory()->for($project)->on('2026-09-03')->create();
    // Nobody is crewed on this shoot at all.

    $this->actingAs($owner, 'staff');

    $shoots = props("/auftraege/{$project->public_id}/shootings")['shoots'];

    expect($shoots[0]['crewConflicts'])->toBe([]);
});

it('flags a studio with a single user so the shoot hides its crew', function () {
    $owner = staffMember(StaffRole::Admin);
    $project = Project::factory()->create();
    $this->actingAs($owner, 'staff');

    expect(props("/auftraege/{$project->public_id}/shootings")['soloStudio'])->toBeTrue();

    User::factory()->create();

    expect(props("/auftraege/{$project->public_id}/shootings")['soloStudio'])->toBeFalse();
});
