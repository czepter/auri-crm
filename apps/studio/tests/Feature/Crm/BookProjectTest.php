<?php

use App\Crm\Actions\BookProject;
use App\Crm\Enums\BookedVia;
use App\Crm\Enums\ShootStatus;
use App\Crm\Events\ProjectBooked;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('creates the job and names both the actor and the trigger', function () {
    $actor = User::factory()->create();
    $project = Project::factory()->create();

    $job = app(BookProject::class)($project, $actor);

    expect($job->booked_by_id)->toBe($actor->id)
        ->and($job->booked_via)->toBe(BookedVia::Manual)
        ->and($job->booked_at)->not->toBeNull()
        ->and($project->fresh()->job->is($job))->toBeTrue();
});

it('moves every uncancelled shoot to booked', function () {
    $project = Project::factory()->create();
    $requested = Shoot::factory()->for($project)->primary()->create();
    $alsoRequested = Shoot::factory()->for($project)->create();

    app(BookProject::class)($project, User::factory()->create());

    expect($requested->fresh()->status)->toBe(ShootStatus::Booked)
        ->and($alsoRequested->fresh()->status)->toBe(ShootStatus::Booked);
});

it('leaves a shoot already past booked untouched', function () {
    // Booking must not undo delivered work.
    $project = Project::factory()->create();
    $delivered = Shoot::factory()->for($project)->atStatus(ShootStatus::Delivered)->create();

    app(BookProject::class)($project, User::factory()->create());

    expect($delivered->fresh()->status)->toBe(ShootStatus::Delivered);
});

it('leaves a cancelled shoot untouched', function () {
    $project = Project::factory()->create();
    $cancelled = Shoot::factory()->for($project)->cancelled('Abgesagt')->create();

    app(BookProject::class)($project, User::factory()->create());

    expect($cancelled->fresh()->status)->toBe(ShootStatus::Requested);
});

it('routes the shoots through the pipeline rather than mass updating them', function () {
    // The audit row per shoot is the evidence that P-03's guard ran.
    $project = Project::factory()->create();
    Shoot::factory()->for($project)->count(2)->create();

    app(BookProject::class)($project, User::factory()->create());

    expect(AuditLog::where('event', 'shoot.status_changed')->count())->toBe(2);
});

it('fires ProjectBooked exactly once and writes the audit row', function () {
    Event::fake([ProjectBooked::class]);
    $project = Project::factory()->create();

    app(BookProject::class)($project, User::factory()->create());

    Event::assertDispatchedTimes(ProjectBooked::class, 1);
    expect(AuditLog::where('event', 'project.booked')->sole()->subject)->toBe($project->public_id);
});

it('is a no-op on a project that already has a job', function () {
    Event::fake([ProjectBooked::class]);
    $project = Project::factory()->create();
    $existing = Job::factory()->for($project)->create();
    $shoot = Shoot::factory()->for($project)->create();

    $result = app(BookProject::class)($project, User::factory()->create());

    Event::assertNotDispatched(ProjectBooked::class);
    expect($result)->toBeNull()
        ->and(Job::count())->toBe(1)
        ->and(Job::sole()->is($existing))->toBeTrue()
        ->and($shoot->fresh()->status)->toBe(ShootStatus::Requested)
        ->and(AuditLog::where('event', 'project.booked')->count())->toBe(0);
});

it('never infers the actor from the ambient session — a null actor stays null even while a staff member is logged in', function () {
    // P-05b's listener passes null on purpose: a signature is a trigger with
    // no staff member behind it, even when it happens to run inside a
    // request a staff member is authenticated on (e.g. the same request in
    // which they marked an installment paid by hand).
    $staff = User::factory()->create();
    $this->actingAs($staff, 'staff');

    $job = app(BookProject::class)(Project::factory()->create(), null);

    expect($job->booked_by_id)->toBeNull();
});

it('records the trigger a caller names', function () {
    // P-05b adds no column; it writes the second value.
    $job = app(BookProject::class)(
        Project::factory()->create(),
        User::factory()->create(),
        BookedVia::Contract,
    );

    expect($job->booked_via)->toBe(BookedVia::Contract);
});
