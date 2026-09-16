<?php

use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ShootStatus;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\InternalNote;
use App\Crm\Models\Job;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Enums\Capability;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function staff(StaffRole $role): User
{
    return User::factory()->withMfa()->create(['role' => $role]);
}

/** A project this person is crewed on, so the "assigned" half can be asserted. */
function crewedProject(User $user): Project
{
    $project = Project::factory()->create();
    $shoot = Shoot::factory()->for($project)->create();
    CrewAssignment::factory()->for($shoot)->for($user)->create();

    return $project;
}

it('lets only the owner commit the studio', function (StaffRole $role, bool $allowed) {
    // The clause that matters most: P-05a shipped booking open to every role
    // in sprint 3, and this is what closes it.
    expect($role->allows(Capability::BookProject))->toBe($allowed);
})->with([
    [StaffRole::Owner, true],
    [StaffRole::Photographer, false],
    [StaffRole::Editor, false],
    [StaffRole::SecondShooter, false],
    [StaffRole::Assistant, false],
]);

it('refuses to book for a role without the capability', function () {
    $project = Project::factory()->create();
    Shoot::factory()->for($project)->create();

    $this->actingAs(staff(StaffRole::Photographer), 'staff')
        ->post("/auftraege/{$project->public_id}/buchung")
        ->assertForbidden();

    expect($project->fresh()->job)->toBeNull();
});

it('lets the owner book', function () {
    $project = Project::factory()->create();
    Shoot::factory()->for($project)->create();

    $this->actingAs(staff(StaffRole::Owner), 'staff')
        ->post("/auftraege/{$project->public_id}/buchung")
        ->assertRedirect();

    expect($project->fresh()->job)->not->toBeNull();
});

it('refuses to book a lost project', function () {
    $project = Project::factory()->create();
    Shoot::factory()->for($project)->create();
    Lead::factory()->for($project)->lost()->create();

    $this->actingAs(staff(StaffRole::Owner), 'staff')
        ->post("/auftraege/{$project->public_id}/buchung")
        ->assertForbidden();

    expect($project->fresh()->job)->toBeNull();
});

it('opens the funnel to every staff role', function (StaffRole $role) {
    // Moving a lead is ordinary studio work and it is reversible. The control
    // on it is the audit trail, not a gate: locking it would mean whoever just
    // got off the phone has to find someone else to type it in.
    $this->actingAs(staff($role), 'staff')->get('/anfragen')->assertOk();
})->with(StaffRole::cases());

it('records who moved a lead, which is what makes the funnel open', function () {
    $nina = staff(StaffRole::SecondShooter);
    $lead = Lead::factory()->atStage(LeadStage::New)->create();

    $this->actingAs($nina, 'staff')
        ->post("/anfragen/{$lead->public_id}/stufe", ['stage' => LeadStage::Contacted->value])
        ->assertRedirect();

    $row = AuditLog::where('event', 'lead.stage_changed')->sole();

    expect($lead->fresh()->stage->key)->toBe(LeadStage::Contacted->value)
        ->and($row->actor_id)->toBe($nina->id)
        ->and($row->context['from'])->toBe('new')
        ->and($row->context['to'])->toBe('contacted');
});

it('keeps the address book to the roles that hold the relationship', function (StaffRole $role, int $status) {
    $this->actingAs(staff($role), 'staff')->get('/kontakte')->assertStatus($status);
})->with([
    [StaffRole::Owner, 200],
    [StaffRole::Photographer, 200],
    [StaffRole::Editor, 403],
    [StaffRole::SecondShooter, 403],
]);

/*
 * The review question this sprint is measured on, in both halves.
 */
it('lets a second shooter reach exactly the shoots they are assigned to', function () {
    $nina = staff(StaffRole::SecondShooter);
    $mine = crewedProject($nina);

    $this->actingAs($nina, 'staff')
        ->get("/auftraege/{$mine->public_id}")
        ->assertOk();
});

it('refuses a second shooter a project they are not crewed on', function () {
    $nina = staff(StaffRole::SecondShooter);
    $theirs = Project::factory()->create();
    Shoot::factory()->for($theirs)->create();

    $this->actingAs($nina, 'staff')
        ->get("/auftraege/{$theirs->public_id}")
        ->assertForbidden();
});

it('shows a second shooter only their own projects in the project list', function () {
    // The screens that list many projects scope rather than refuse, so the
    // list has to be filtered as well as the detail page guarded. C-02 (#236)
    // moved that list off the Übersicht and onto /projekte.
    $nina = staff(StaffRole::SecondShooter);
    $mine = crewedProject($nina);
    Job::factory()->for($mine)->create();

    $theirs = Project::factory()->create(['name' => 'Fremdes Projekt']);
    Job::factory()->for($theirs)->create();
    Shoot::factory()->for($theirs)->create();

    $this->actingAs($nina, 'staff');

    expect(collect(props('/projekte')['projects'])->pluck('name')->all())
        ->not->toContain('Fremdes Projekt');
});

it('lets an editor move post-production but never commit the studio', function () {
    $editor = staff(StaffRole::Editor);
    $project = Project::factory()->create();
    $shoot = Shoot::factory()->for($project)->atStatus(ShootStatus::Culling)->create();

    $this->actingAs($editor, 'staff')
        ->post("/shootings/{$shoot->public_id}/status", ['status' => 'editing'])
        ->assertRedirect();

    expect($shoot->fresh()->status->key)->toBe(ShootStatus::Editing->value);

    // Booking is the line no amount of after-the-fact logging substitutes for.
    $this->post("/auftraege/{$project->public_id}/buchung")->assertForbidden();
});

it('refuses a second shooter the shoot controls even on their own project', function () {
    // Being on the day is not authority over the day.
    $nina = staff(StaffRole::SecondShooter);
    $project = crewedProject($nina);
    $shoot = $project->shoots()->sole();

    $this->actingAs($nina, 'staff')
        ->post("/shootings/{$shoot->public_id}/status", ['status' => 'booked'])
        ->assertForbidden();

    $this->post("/shootings/{$shoot->public_id}/crew", [
        'user' => $nina->public_id,
        'role' => 'assistant',
    ])->assertForbidden();
});

it('lets a second shooter work the tasks and notes of their own project', function () {
    $nina = staff(StaffRole::SecondShooter);
    $project = crewedProject($nina);
    $task = Task::factory()->for($project)->create();

    $this->actingAs($nina, 'staff')
        ->post("/aufgaben/{$task->public_id}/erledigt")
        ->assertRedirect();

    expect($task->fresh()->isDone())->toBeTrue();

    $this->post("/auftraege/{$project->public_id}/notizen", ['body' => 'Gegenlicht.'])
        ->assertRedirect();

    expect(InternalNote::count())->toBe(1);
});

it('refuses a second shooter the tasks of a project they are not on', function () {
    $nina = staff(StaffRole::SecondShooter);
    $task = Task::factory()->create();

    $this->actingAs($nina, 'staff')
        ->post("/aufgaben/{$task->public_id}/erledigt")
        ->assertForbidden();

    expect($task->fresh()->isDone())->toBeFalse();
});

it('leaves a denied control out of the markup rather than hiding it', function () {
    // The milestone's own criterion. A control rendered and hidden is a
    // control someone can find with the element inspector.
    $project = Project::factory()->create();
    Shoot::factory()->for($project)->create();

    $this->actingAs(staff(StaffRole::Photographer), 'staff');
    $header = props("/auftraege/{$project->public_id}")['header'];

    expect($header['canBook'])->toBeFalse()
        ->and($header['canManageShoots'])->toBeTrue();

    $this->actingAs(staff(StaffRole::Owner), 'staff');

    expect(props("/auftraege/{$project->public_id}")['header']['canBook'])->toBeTrue();
});

it('tells the sidebar only what this role may reach', function () {
    $this->actingAs(staff(StaffRole::SecondShooter), 'staff');

    expect(props('/')['auth']['can'])
        ->toContain('manage_leads')
        ->not->toContain('manage_contacts')
        ->not->toContain('book_project')
        ->toContain('manage_tasks');
});

it('opens the package catalog to every staff role', function (StaffRole $role) {
    // Defining or retiring an offering is ordinary studio work and it is
    // reversible — the same shape as the funnel. AuditLogger records who
    // changed a package, which is the accountability a gate would only
    // have prevented being asked for.
    $this->actingAs(staff($role), 'staff')->get('/pakete')->assertOk();
})->with(StaffRole::cases());

it('opens document templates to the roles ManageShoots already trusts, and refuses the crew roles', function (StaffRole $role, int $status) {
    // Narrower than the catalog on purpose (Capability::ManageContracts's own
    // doc comment).
    $this->actingAs(staff($role), 'staff')->get('/vorlagen?typ=dokumente')->assertStatus($status);
})->with([
    [StaffRole::Owner, 200],
    [StaffRole::Photographer, 200],
    [StaffRole::Editor, 200],
    [StaffRole::SecondShooter, 404],
    [StaffRole::Assistant, 404],
]);

it('gives every role a capability set that is a subset of the owner', function () {
    // A role that could do something the owner cannot would be a hole in the
    // model rather than a feature.
    $owner = array_map(fn (Capability $c) => $c->value, StaffRole::Owner->capabilities());

    foreach (StaffRole::cases() as $role) {
        $held = array_map(fn (Capability $c) => $c->value, $role->capabilities());

        expect(array_diff($held, $owner))->toBe([]);
    }
});
