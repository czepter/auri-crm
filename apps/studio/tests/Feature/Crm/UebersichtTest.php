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
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
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

it('previews the shoot after the hero shoot in the calendar footer', function () {
    $project = Project::factory()->create();
    Job::factory()->for($project)->create();
    Shoot::factory()->for($project)->on(now()->addDays(5)->format('Y-m-d'))->create(['label' => 'Hochzeitstag']);
    Shoot::factory()->for($project)->on(now()->addDays(20)->format('Y-m-d'))->create(['label' => 'Portrait Vogt']);

    $upcoming = props('/')['upcomingShoots'];

    expect($upcoming)->toHaveCount(1)
        ->and($upcoming[0]['label'])->toBe('Portrait Vogt')
        ->and($upcoming[0]['date'])->toBe(now()->addDays(20)->format('Y-m-d'));
});

it('does not preview a shoot dated before the hero shoot', function () {
    // Chronologically earlier and already happened — not a future shoot the
    // hero could ever have been, so this only exercises the "after the
    // hero's date" filter, not the hero-selection query.
    $project = Project::factory()->create();
    Job::factory()->for($project)->create();
    Shoot::factory()->for($project)->on(now()->addDays(5)->format('Y-m-d'))->create(['label' => 'Hochzeitstag']);
    Shoot::factory()->for($project)->on(now()->subDays(10)->format('Y-m-d'))->create(['label' => 'Verlobungsshooting']);

    expect(props('/')['upcomingShoots'])->toBeEmpty();
});

it('does not preview a cancelled shoot', function () {
    $project = Project::factory()->create();
    Job::factory()->for($project)->create();
    Shoot::factory()->for($project)->on(now()->addDays(5)->format('Y-m-d'))->create(['label' => 'Hochzeitstag']);
    Shoot::factory()->for($project)->on(now()->addDays(10)->format('Y-m-d'))->cancelled('Termin abgesagt')->create();

    expect(props('/')['upcomingShoots'])->toBeEmpty();
});

it('does not preview a shoot on a project that is not booked', function () {
    // An unbooked date is a request, not a commitment — the same rule
    // nextShoot() already applies to the hero itself.
    $project = Project::factory()->create();
    Job::factory()->for($project)->create();
    Shoot::factory()->for($project)->on(now()->addDays(5)->format('Y-m-d'))->create(['label' => 'Hochzeitstag']);

    $unbooked = Project::factory()->create();
    Shoot::factory()->for($unbooked)->on(now()->addDays(8)->format('Y-m-d'))->create();

    expect(props('/')['upcomingShoots'])->toBeEmpty();
});

it('previews nothing when there is no hero shoot', function () {
    // No hero means "after the next one" has no meaning — an empty list,
    // not every future shoot.
    $project = Project::factory()->create();
    Job::factory()->for($project)->create();
    Shoot::factory()->for($project)->on(now()->subDay()->format('Y-m-d'))->create();

    expect(props('/')['nextShoot'])->toBeNull()
        ->and(props('/')['upcomingShoots'])->toBeEmpty();
});

it('caps the calendar preview at two shoots', function () {
    $project = Project::factory()->create();
    Job::factory()->for($project)->create();
    Shoot::factory()->for($project)->on(now()->addDays(5)->format('Y-m-d'))->create(['label' => 'Hochzeitstag']);
    Shoot::factory()->for($project)->on(now()->addDays(10)->format('Y-m-d'))->create(['label' => 'A']);
    Shoot::factory()->for($project)->on(now()->addDays(15)->format('Y-m-d'))->create(['label' => 'B']);
    Shoot::factory()->for($project)->on(now()->addDays(20)->format('Y-m-d'))->create(['label' => 'C']);

    $upcoming = props('/')['upcomingShoots'];

    expect($upcoming)->toHaveCount(2)
        ->and(collect($upcoming)->pluck('label')->all())->toBe(['A', 'B']);
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

it('sums the pipeline from quotes still in play', function () {
    // Draft and sent are in play; accepted is won and superseded is gone.
    $project = Project::factory()->create();
    Quote::factory()->for($project)->create(['status' => QuoteStatus::Sent, 'total' => 4875000]);
    Quote::factory()->for($project)->create(['status' => QuoteStatus::Accepted, 'total' => 900000]);

    expect(props('/')['pipeline'])->toBe(4875000);
});

it('sums what is overdue', function () {
    $job = Job::factory()->create();
    $plan = PaymentPlan::factory()->for($job)->create(['total' => 290000]);
    PaymentInstallment::factory()->for($plan, 'plan')->create([
        'amount' => 290000,
        'due_on' => now()->subDays(5)->toDateString(),
    ]);

    expect(props('/')['overdueTotal'])->toBe(290000);
});

it('sums what is expected by month end, excluding what is already overdue', function () {
    $inWindow = Job::factory()->create();
    $plan = PaymentPlan::factory()->for($inWindow)->create(['total' => 300000]);
    PaymentInstallment::factory()->for($plan, 'plan')->count(2)->create([
        'amount' => 150000,
        'due_on' => now()->addDays(3)->toDateString(),
    ]);

    // Already overdue: has its own tile, must not double up in this one.
    $overdue = Job::factory()->create();
    $overduePlan = PaymentPlan::factory()->for($overdue)->create(['total' => 90000]);
    PaymentInstallment::factory()->for($overduePlan, 'plan')->create([
        'amount' => 90000,
        'due_on' => now()->subDay()->toDateString(),
    ]);

    // Due next month: outside the window.
    $nextMonth = Job::factory()->create();
    $nextMonthPlan = PaymentPlan::factory()->for($nextMonth)->create(['total' => 50000]);
    PaymentInstallment::factory()->for($nextMonthPlan, 'plan')->create([
        'amount' => 50000,
        'due_on' => now()->addMonthNoOverflow()->startOfMonth()->addDays(5)->toDateString(),
    ]);

    $props = props('/');

    expect($props['expectedByMonthEndTotal'])->toBe(300000)
        ->and($props['expectedByMonthEndCount'])->toBe(2);
});

it('reports the acceptance rate over the trailing twelve months', function () {
    $project = Project::factory()->create();
    Quote::factory()->for($project)->count(5)->create([
        'status' => QuoteStatus::Sent,
        'sent_at' => now()->subMonths(3),
    ]);
    Quote::factory()->for($project)->count(3)->create([
        'status' => QuoteStatus::Accepted,
        'sent_at' => now()->subMonths(3),
        'accepted_at' => now()->subMonths(2),
    ]);
    // Older than the window; must not move the number.
    Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Accepted,
        'sent_at' => now()->subMonths(18),
        'accepted_at' => now()->subMonths(17),
    ]);

    expect(props('/')['acceptanceRate'])->toBe(38);
});

it('reports no acceptance rate when nothing has been sent', function () {
    // A percentage of nothing is not zero percent; it is no number at all,
    // and the tile does not render.
    expect(props('/')['acceptanceRate'])->toBeNull();
});

it('counts threads where the client spoke last', function () {
    // Unbeantwortet, not ungelesen: ThreadParticipant carries no last_read_at
    // and this does not add one. An unanswered thread is a fact about the
    // conversation; an unread one is a fact about a person.
    $project = Project::factory()->create();
    $contact = Contact::factory()->create();

    $waiting = Thread::factory()->for($project)->create();
    Message::factory()->for($waiting)->create(['contact_id' => $contact->id, 'author_id' => null]);

    $answered = Thread::factory()->for($project)->create();
    Message::factory()->for($answered)->create(['contact_id' => $contact->id, 'author_id' => null]);
    Message::factory()->for($answered)->create(['contact_id' => null, 'author_id' => $this->staff->id]);

    expect(props('/')['unanswered'])->toBe(1);
});

it('does not count a thread with no messages at all', function () {
    // The obvious edge a naive "max(created_at) over this thread's messages"
    // formulation gets wrong: no message means no newest-message fact to
    // check, so the thread must not count as unanswered.
    Thread::factory()->for(Project::factory())->create();

    expect(props('/')['unanswered'])->toBe(0);
});
