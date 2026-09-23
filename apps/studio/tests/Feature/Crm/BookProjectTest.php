<?php

use App\Crm\Actions\BookProject;
use App\Crm\Actions\UnbookProject;
use App\Crm\Enums\BookedVia;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Enums\ShootStatus;
use App\Crm\Events\ProjectBooked;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\Shoot;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Payments\Models\PaymentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('books the project and names both the actor and the trigger', function () {
    $actor = User::factory()->create();
    $project = ClientCase::factory()->create();

    app(BookProject::class)($project, $actor);

    $project->refresh();
    expect($project->booked_by_id)->toBe($actor->id)
        ->and($project->booked_via)->toBe(BookedVia::Manual)
        ->and($project->isBooked())->toBeTrue();
});

it('moves every uncancelled shoot to booked', function () {
    $project = ClientCase::factory()->create();
    $requested = Shoot::factory()->for($project, 'case')->primary()->create();
    $alsoRequested = Shoot::factory()->for($project, 'case')->create();

    app(BookProject::class)($project, User::factory()->create());

    expect($requested->fresh()->status->key)->toBe(ShootStatus::Booked->value)
        ->and($alsoRequested->fresh()->status->key)->toBe(ShootStatus::Booked->value);
});

it('leaves a shoot already past booked untouched', function () {
    // Booking must not undo delivered work.
    $project = ClientCase::factory()->create();
    $delivered = Shoot::factory()->for($project, 'case')->atStatus(ShootStatus::Delivered)->create();

    app(BookProject::class)($project, User::factory()->create());

    expect($delivered->fresh()->status->key)->toBe(ShootStatus::Delivered->value);
});

it('leaves a cancelled shoot untouched', function () {
    $project = ClientCase::factory()->create();
    $cancelled = Shoot::factory()->for($project, 'case')->cancelled('Abgesagt')->create();

    app(BookProject::class)($project, User::factory()->create());

    expect($cancelled->fresh()->status->key)->toBe(ShootStatus::Requested->value);
});

it('routes the shoots through the pipeline rather than mass updating them', function () {
    // The audit row per shoot is the evidence that P-03's guard ran.
    $project = ClientCase::factory()->create();
    Shoot::factory()->for($project, 'case')->count(2)->create();

    app(BookProject::class)($project, User::factory()->create());

    expect(AuditLog::where('event', 'shoot.status_changed')->count())->toBe(2);
});

it('books a shoot added to an already booked project', function () {
    $project = ClientCase::factory()->create();
    app(BookProject::class)($project, User::factory()->create());

    $this->actingAs(User::factory()->withMfa()->create(), 'staff')
        ->post("/auftraege/{$project->public_id}/shootings", ['kind' => 'Feier'])
        ->assertRedirect();

    expect($project->shoots()->sole()->status->key)->toBe(ShootStatus::Booked->value);
});

it('shows a booked project in the portal unless its contact is held back', function () {
    $open = ClientCase::factory()->create();
    $open->attachContact(Contact::factory()->create(), CaseContactRole::Primary);
    $held = ClientCase::factory()->create();
    $held->attachContact(Contact::factory()->create(['portal_overrides' => ['portal_visible' => false]]), CaseContactRole::Primary);

    app(BookProject::class)($open);
    app(BookProject::class)($held);

    expect($open->fresh()->portal_visible)->toBeTrue()
        ->and($held->fresh()->portal_visible)->toBeFalse();
});

it('fires ProjectBooked exactly once and writes the audit row', function () {
    Event::fake([ProjectBooked::class]);
    $project = ClientCase::factory()->create();

    app(BookProject::class)($project, User::factory()->create());

    Event::assertDispatchedTimes(ProjectBooked::class, 1);
    expect(AuditLog::where('event', 'project.booked')->sole()->subject)->toBe($project->public_id);
});

it('is a no-op on a project that is already booked', function () {
    Event::fake([ProjectBooked::class]);
    $project = markBooked(ClientCase::factory()->create(), ['booked_at' => '2026-05-02 10:00']);
    $shoot = Shoot::factory()->for($project, 'case')->create();

    $result = app(BookProject::class)($project, User::factory()->create());

    Event::assertNotDispatched(ProjectBooked::class);
    expect($result)->toBeNull()
        ->and($project->fresh()->booked_at->toDateString())->toBe('2026-05-02')
        ->and($shoot->fresh()->status->key)->toBe(ShootStatus::Requested->value)
        ->and(AuditLog::where('event', 'project.booked')->count())->toBe(0);
});

it('keeps the first booking when a second trigger books the same project', function () {
    Event::fake([ProjectBooked::class]);
    $project = ClientCase::factory()->create();
    $first = User::factory()->create();

    app(BookProject::class)(ClientCase::find($project->id), $first);
    $second = app(BookProject::class)(ClientCase::find($project->id), null, BookedVia::Contract);

    Event::assertDispatchedTimes(ProjectBooked::class, 1);
    expect($second)->toBeNull()
        ->and($project->fresh()->booked_by_id)->toBe($first->id)
        ->and($project->fresh()->booked_via)->toBe(BookedVia::Manual)
        ->and(AuditLog::where('event', 'project.booked')->count())->toBe(1);
});

it('never infers the actor from the ambient session — a null actor stays null even while a staff member is logged in', function () {
    // P-05b's listener passes null on purpose: a signature is a trigger with
    // no staff member behind it, even when it happens to run inside a
    // request a staff member is authenticated on (e.g. the same request in
    // which they marked an installment paid by hand).
    $staff = User::factory()->create();
    $this->actingAs($staff, 'staff');

    $project = app(BookProject::class)(ClientCase::factory()->create(), null);

    expect($project->fresh()->booked_by_id)->toBeNull();
});

it('records the trigger a caller names', function () {
    // P-05b adds no column; it writes the second value.
    $project = app(BookProject::class)(
        ClientCase::factory()->create(),
        User::factory()->create(),
        BookedVia::Contract,
    );

    expect($project->fresh()->booked_via)->toBe(BookedVia::Contract);
});

it('unbooks a project back into an inquiry', function () {
    $project = markBooked(ClientCase::factory()->create());

    app(UnbookProject::class)($project, User::factory()->create());

    expect($project->fresh()->isBooked())->toBeFalse()
        ->and($project->fresh()->booked_by_id)->toBeNull()
        ->and(AuditLog::where('event', 'project.unbooked')->count())->toBe(1);
});

it('refuses to unbook a project with a payment plan', function () {
    $project = markBooked(ClientCase::factory()->create());
    PaymentPlan::factory()->for($project, 'case')->create();

    expect(fn () => app(UnbookProject::class)($project, User::factory()->create()))->toThrow(DomainException::class);
    expect($project->fresh()->isBooked())->toBeTrue();
});

it('refuses to unbook for a role that may not book', function () {
    $project = markBooked(ClientCase::factory()->create());

    expect(fn () => app(UnbookProject::class)($project, staffMember(StaffRole::Manager)))->toThrow(DomainException::class);
    expect($project->fresh()->isBooked())->toBeTrue();
});
