<?php

use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ShootStatus;
use App\Crm\Models\ClientCase;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\InternalNote;
use App\Crm\Models\Lead;
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
function crewedProject(User $user): ClientCase
{
    $project = ClientCase::factory()->create();
    $shoot = Shoot::factory()->for($project, 'case')->create();
    CrewAssignment::factory()->for($shoot)->for($user)->create();

    return $project;
}

it('lets only the owner commit the studio', function (StaffRole $role, bool $allowed) {
    // The clause that matters most: P-05a shipped booking open to every role
    // in sprint 3, and this is what closes it.
    expect($role->allows(Capability::BookProject))->toBe($allowed);
})->with([
    [StaffRole::Admin, true],
    [StaffRole::Manager, false],
    [StaffRole::Contributor, false],
    [StaffRole::Reader, false],
]);

it('refuses to book for a role without the capability', function () {
    $project = ClientCase::factory()->create();
    Shoot::factory()->for($project, 'case')->create();

    $this->actingAs(staff(StaffRole::Manager), 'staff')
        ->post("/auftraege/{$project->public_id}/buchung")
        ->assertForbidden();

    expect($project->fresh()->isBooked())->toBeFalse();
});

it('lets the owner book', function () {
    $project = ClientCase::factory()->create();
    Shoot::factory()->for($project, 'case')->create();

    $this->actingAs(staff(StaffRole::Admin), 'staff')
        ->post("/auftraege/{$project->public_id}/buchung")
        ->assertRedirect();

    expect($project->fresh()->isBooked())->toBeTrue();
});

it('refuses to book a lost project', function () {
    $project = ClientCase::factory()->create();
    Shoot::factory()->for($project, 'case')->create();
    Lead::factory()->for($project, 'case')->lost()->create();

    $this->actingAs(staff(StaffRole::Admin), 'staff')
        ->post("/auftraege/{$project->public_id}/buchung")
        ->assertForbidden();

    expect($project->fresh()->isBooked())->toBeFalse();
});

it('opens the funnel to every staff role', function (StaffRole $role) {
    // Moving a lead is ordinary studio work and it is reversible. The control
    // on it is the audit trail, not a gate: locking it would mean whoever just
    // got off the phone has to find someone else to type it in.
    $this->actingAs(staff($role), 'staff')->get('/anfragen')->assertOk();
})->with(StaffRole::cases());

it('records who moved a lead, which is what makes the funnel open', function () {
    $nina = staff(StaffRole::Contributor);
    $lead = Lead::factory()->atStage(LeadStage::New)->create();

    $this->actingAs($nina, 'staff')
        ->post("/anfragen/{$lead->case->public_id}/stufe", ['stage' => LeadStage::Contacted->value])
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
    [StaffRole::Admin, 200],
    [StaffRole::Manager, 200],
    [StaffRole::Contributor, 403],
    [StaffRole::Reader, 403],
]);

/*
 * The review question this sprint is measured on, in both halves.
 */
it('lets a second shooter reach exactly the shoots they are assigned to', function () {
    $nina = staff(StaffRole::Contributor);
    $mine = crewedProject($nina);

    $this->actingAs($nina, 'staff')
        ->get("/auftraege/{$mine->public_id}")
        ->assertOk();
});

it('refuses a second shooter a project they are not crewed on', function () {
    $nina = staff(StaffRole::Contributor);
    $theirs = ClientCase::factory()->create();
    Shoot::factory()->for($theirs, 'case')->create();

    $this->actingAs($nina, 'staff')
        ->get("/auftraege/{$theirs->public_id}")
        ->assertForbidden();
});

it('shows a second shooter only their own projects in the project list', function () {
    // The screens that list many projects scope rather than refuse, so the
    // list has to be filtered as well as the detail page guarded. C-02 (#236)
    // moved that list off the Übersicht and onto /projekte.
    $nina = staff(StaffRole::Contributor);
    $mine = crewedProject($nina);
    markBooked($mine);

    $theirs = ClientCase::factory()->create(['name' => 'Fremdes Projekt']);
    markBooked($theirs);
    Shoot::factory()->for($theirs, 'case')->create();

    $this->actingAs($nina, 'staff');

    expect(collect(props('/projekte')['projects'])->pluck('name')->all())
        ->not->toContain('Fremdes Projekt');
});

it('lets a contributor move a shoot they are crewed on, but never reassign the crew', function () {
    $nina = staff(StaffRole::Contributor);
    $project = crewedProject($nina);
    $shoot = $project->shoots()->sole()->fresh();
    $shoot->update(['status' => ShootStatus::Culling]);

    $this->actingAs($nina, 'staff')
        ->post("/shootings/{$shoot->public_id}/status", ['status' => 'editing'])
        ->assertRedirect();

    $this->post("/shootings/{$shoot->public_id}/crew", [
        'user' => $nina->public_id,
        'role' => 'contributor',
    ])->assertForbidden();

    // Booking is the line no amount of after-the-fact logging substitutes for.
    $this->post("/auftraege/{$project->public_id}/buchung")->assertForbidden();
});

it('lets a second shooter work the tasks and notes of their own project', function () {
    $nina = staff(StaffRole::Contributor);
    $project = crewedProject($nina);
    $task = Task::factory()->for($project, 'case')->create();

    $this->actingAs($nina, 'staff')
        ->post("/aufgaben/{$task->public_id}/erledigt")
        ->assertRedirect();

    expect($task->fresh()->isDone())->toBeTrue();

    $this->post("/auftraege/{$project->public_id}/notizen", ['body' => 'Gegenlicht.'])
        ->assertRedirect();

    expect(InternalNote::count())->toBe(1);
});

it('refuses a second shooter the tasks of a project they are not on', function () {
    $nina = staff(StaffRole::Contributor);
    $task = Task::factory()->create();

    $this->actingAs($nina, 'staff')
        ->post("/aufgaben/{$task->public_id}/erledigt")
        ->assertForbidden();

    expect($task->fresh()->isDone())->toBeFalse();
});

it('leaves a denied control out of the markup rather than hiding it', function () {
    // The milestone's own criterion. A control rendered and hidden is a
    // control someone can find with the element inspector.
    $project = ClientCase::factory()->create();
    Shoot::factory()->for($project, 'case')->create();

    $this->actingAs(staff(StaffRole::Manager), 'staff');
    $header = props("/auftraege/{$project->public_id}")['header'];

    expect($header['canBook'])->toBeFalse()
        ->and($header['canManageShoots'])->toBeTrue();

    $this->actingAs(staff(StaffRole::Admin), 'staff');

    expect(props("/auftraege/{$project->public_id}")['header']['canBook'])->toBeTrue();
});

it('tells the sidebar only what this role may reach', function () {
    $this->actingAs(staff(StaffRole::Contributor), 'staff');

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
    [StaffRole::Admin, 200],
    [StaffRole::Manager, 200],
    [StaffRole::Contributor, 404],
    [StaffRole::Reader, 404],
]);

it('gives every role a capability set that is a subset of the owner', function () {
    // A role that could do something the owner cannot would be a hole in the
    // model rather than a feature.
    $owner = array_map(fn (Capability $c) => $c->value, StaffRole::Admin->capabilities());

    foreach (StaffRole::cases() as $role) {
        $held = array_map(fn (Capability $c) => $c->value, $role->capabilities());

        expect(array_diff($held, $owner))->toBe([]);
    }
});
