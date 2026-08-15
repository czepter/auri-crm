<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ShootStatus;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Contact;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Gallery\Models\Gallery;
use App\Models\User;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

it('leads with the next shooting day of a booked project', function () {
    $project = Project::factory()->create(['name' => 'Meier & Kowalski']);
    Job::factory()->for($project)->create();
    $shoot = Shoot::factory()->for($project)
        ->on(now()->addDays(5)->format('Y-m-d'))
        ->atStatus(ShootStatus::Prep)
        ->create(['label' => 'Hochzeitstag', 'call_time' => '09:00']);
    CrewAssignment::factory()->for($shoot)->create();

    $next = props('/')['nextShoot'];

    expect($next['title'])->toBe('Hochzeitstag')
        ->and($next['projectName'])->toBe('Meier & Kowalski')
        ->and($next['callTime'])->toBe('09:00');
});

it('never leads with a shoot whose project is not booked', function () {
    // An unbooked date is a request, not a commitment.
    $project = Project::factory()->create();
    Shoot::factory()->for($project)->on(now()->addDay()->format('Y-m-d'))->create();

    expect(props('/')['nextShoot'])->toBeNull();
});

it('never leads with a day already past', function () {
    $project = Project::factory()->create();
    Job::factory()->for($project)->create();
    Shoot::factory()->for($project)->on(now()->subDay()->format('Y-m-d'))->create();

    expect(props('/')['nextShoot'])->toBeNull();
});

it('states readiness from real facts rather than invented labels', function () {
    $project = Project::factory()->create();
    Job::factory()->for($project)->create();
    $shoot = Shoot::factory()->for($project)->on(now()->addDays(3)->format('Y-m-d'))->create();
    Task::factory()->for($project)->create(['shoot_id' => $shoot->id, 'title' => 'Crew-Briefing']);

    $readiness = props('/')['nextShoot']['readiness'];

    // Crew is unassigned and the briefing is open, so both read as not done.
    expect(collect($readiness)->pluck('label')->all())->toBe(['Crew', 'Crew-Briefing'])
        ->and(collect($readiness)->every(fn ($mark) => $mark['done'] === false))->toBeTrue();
});

it('lists what is owed, overdue first by date', function () {
    // Three projects, because the worklist carries one row each — the
    // ordering is what this asserts, not the grouping.
    Task::factory()->due(now()->subDays(2)->format('Y-m-d'))->create(['title' => 'Spät']);
    Task::factory()->due(now()->addDays(9)->format('Y-m-d'))->create(['title' => 'Später']);
    Task::factory()->done()->create(['title' => 'Erledigt']);

    $tasks = props('/')['tasks'];

    expect(collect($tasks)->pluck('title')->all())->toBe(['Spät', 'Später'])
        ->and($tasks[0]['isOverdue'])->toBeTrue()
        ->and($tasks[1]['isOverdue'])->toBeFalse();
});

it('caps the worklist at six rows, not at one row per project', function () {
    // Mockup 01 lists the same wedding twice in one block: two different
    // things are owed on it, and each needs its own button. One row per
    // project reads well on a busy board and empties the screen on a studio
    // with a single project in production — which is every studio in its
    // first month, and the state this screen gets judged in.
    $loud = Project::factory()->create(['name' => 'Laut']);
    Task::factory()->for($loud)->count(9)->due(now()->subDays(3)->format('Y-m-d'))->create();

    $quiet = Project::factory()->create(['name' => 'Leise']);
    Task::factory()->for($quiet)->due(now()->subDay()->format('Y-m-d'))->create();

    $props = props('/');

    // The nine older overdue tasks outrank the newer one, so the cap is what
    // keeps 'Leise' off the screen — not a per-project rule.
    expect(collect($props['tasks'])->pluck('projectName')->all())->toBe(array_fill(0, 6, 'Laut'))
        ->and($props['openTasks'])->toBe(10);
});

it('does not call a task due today overdue', function () {
    // Today is the day's work, not a failure.
    Task::factory()->due(now()->format('Y-m-d'))->create();

    expect(props('/')['tasks'][0]['isOverdue'])->toBeFalse();
});

it('carries the production board, one row per booked project', function () {
    $project = Project::factory()->create();
    Job::factory()->for($project)->create();
    Shoot::factory()->for($project)->atStatus(ShootStatus::Delivered)->create();
    Shoot::factory()->for($project)->atStatus(ShootStatus::Culling)->create();

    $production = props('/')['production'];

    expect($production)->toHaveCount(1)
        ->and($production[0]['status'])->toBe('culling');
});

it('completes and reopens a task from the worklist', function () {
    $task = Task::factory()->create();

    $this->post("/aufgaben/{$task->public_id}/erledigt")->assertRedirect();
    expect($task->fresh()->isDone())->toBeTrue()
        ->and($task->fresh()->completed_by_id)->toBe($this->staff->id);

    $this->post("/aufgaben/{$task->public_id}/erledigt")->assertRedirect();
    expect($task->fresh()->isDone())->toBeFalse();
});

it('never puts an integer identifier into the übersicht', function () {
    $project = Project::factory()->create();
    Job::factory()->for($project)->create();
    $shoot = Shoot::factory()->for($project)->on(now()->addDay()->format('Y-m-d'))->create();
    CrewAssignment::factory()->for($shoot)->create();
    Task::factory()->for($project)->create(['shoot_id' => $shoot->id, 'audience' => TaskAudience::Client]);

    expect(integerIdPaths(props('/')))->toBe([]);
});

it('carries the Anfragen & Angebote column, free of integer identifiers', function () {
    $project = Project::factory()->create(['name' => 'Duhnkrack & W.']);
    $project->attachContact(Contact::factory()->create(), ProjectContactRole::Primary);
    Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Accepted,
        'accepted_at' => now()->subDay(),
    ]);

    $rows = props('/')['inquiries'];

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['projectId'])->toBe($project->public_id)
        ->and($rows[0]['who'])->toBe('Duhnkrack & W.');
});

it('shows no Anfragen & Angebote column when nothing is owed on an offer', function () {
    // "Absent, not empty" — S-01's rule, and the reason this screen is a
    // worklist rather than a dashboard of zeroes.
    expect(props('/')['inquiries'])->toBeEmpty();
});

it('carries the Zahlungen column', function () {
    $project = Project::factory()->create(['name' => 'Sievers–Brandt']);
    $project->attachContact(Contact::factory()->create(['phone' => '+4940123']), ProjectContactRole::Primary);
    $job = Job::factory()->for($project)->create();
    $plan = PaymentPlan::factory()->for($job)->create(['total' => 110000]);
    PaymentInstallment::factory()->for($plan, 'plan')->create([
        'amount' => 110000,
        'due_on' => now()->subDays(20)->toDateString(),
        'overdue_notified_at' => now()->subDays(9),
    ]);

    expect(props('/')['payments'])->toHaveCount(1);
});

it('shows no Zahlungen column when nothing is behind', function () {
    expect(props('/')['payments'])->toBeEmpty();
});

it('carries the Nach der Lieferung column', function () {
    $project = Project::factory()->create(['name' => "Hensel Hochzeit '25"]);
    Gallery::factory()->for($project)->create(['expires_at' => now()->addDays(12)]);

    expect(props('/')['afterDelivery'])->toHaveCount(1);
});

it('shows no Nach der Lieferung column when no occasion is open', function () {
    expect(props('/')['afterDelivery'])->toBeEmpty();
});
