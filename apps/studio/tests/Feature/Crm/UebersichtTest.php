<?php

use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Enums\ShootStatus;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Contact;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Job;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Engagement\Models\Message;
use App\Engagement\Models\Thread;
use App\Gallery\Models\Gallery;
use App\Models\Absence;
use App\Models\User;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

function leadAtStage(LeadStage $stage): Lead
{
    return Lead::factory()->for(Project::factory()->wedding()->create())->atStage($stage)->create();
}

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

// C-04 (#238): "Diese Woche" is the week itself, not a sentence about it —
// the calendar-preview footer is gone with the rest of the summarising ones.
it('lists this week\'s shooting days in time order', function () {
    $project = Project::factory()->create(['name' => 'Meier & Kowalski']);
    Job::factory()->for($project)->create();
    Shoot::factory()->for($project)->on(now()->addDays(4)->format('Y-m-d'))->create(['call_time' => '14:00']);
    Shoot::factory()->for($project)->on(now()->addDays(2)->format('Y-m-d'))->create(['call_time' => '09:00']);

    $week = props('/')['week'];

    expect(collect($week)->pluck('when')->all())->toBe([
        now()->addDays(2)->format('Y-m-d').' 09:00',
        now()->addDays(4)->format('Y-m-d').' 14:00',
    ]);
});

it('leaves out what is further off than a week, cancelled, or unbooked', function () {
    $booked = Project::factory()->create();
    Job::factory()->for($booked)->create();
    Shoot::factory()->for($booked)->on(now()->addDays(3)->format('Y-m-d'))->create();
    Shoot::factory()->for($booked)->on(now()->addDays(20)->format('Y-m-d'))->create();
    Shoot::factory()->for($booked)->on(now()->addDays(4)->format('Y-m-d'))->cancelled('Abgesagt')->create();

    $unbooked = Project::factory()->create();
    Shoot::factory()->for($unbooked)->on(now()->addDays(5)->format('Y-m-d'))->create();

    expect(props('/')['week'])->toHaveCount(1);
});

// An absence is an entry on the day it costs, dimmed. Announced above the
// week it said nothing about which day that is, which is the only thing
// anyone reads the line for.
it('puts an absence in the week as its own entry', function () {
    Absence::factory()->create([
        'user_id' => User::factory()->create(['name' => 'Nina Brandt'])->id,
        'starts_on' => now()->addDays(2)->toDateString(),
        'ends_on' => now()->addDays(3)->toDateString(),
    ]);

    $week = props('/')['week'];

    expect($week)->toHaveCount(1)
        ->and($week[0]['what'])->toBe('Nina Brandt')
        ->and($week[0]['away'])->toBeTrue();
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

it('caps the decisions at five, not at one row per project', function () {
    // The draft lists the same wedding twice in one field: two different things
    // are owed on it, and each needs its own button. One row per project reads
    // well on a busy board and empties the screen on a studio with a single
    // project in production — which is every studio in its first month, and the
    // state this screen gets judged in.
    $loud = Project::factory()->create(['name' => 'Laut']);
    Task::factory()->for($loud)->count(9)->due(now()->subDays(3)->format('Y-m-d'))->create();

    $quiet = Project::factory()->create(['name' => 'Leise']);
    Task::factory()->for($quiet)->due(now()->subDay()->format('Y-m-d'))->create();

    $props = props('/');

    // The nine older overdue tasks outrank the newer one, so the cap is what
    // keeps 'Leise' off the screen — not a per-project rule.
    expect(collect($props['tasks'])->pluck('projectName')->all())->toBe(array_fill(0, 5, 'Laut'))
        ->and($props['openTasks'])->toBe(10);
});

it('does not call a task due today overdue', function () {
    // Today is the day's work, not a failure.
    Task::factory()->due(now()->format('Y-m-d'))->create();

    expect(props('/')['tasks'][0]['isOverdue'])->toBeFalse();
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

it('carries the Galerien field', function () {
    $project = Project::factory()->create(['name' => "Hensel Hochzeit '25"]);
    Gallery::factory()->for($project)->create(['expires_at' => now()->addDays(12)]);

    expect(props('/')['galleries'])->toHaveCount(1);
});

it('shows no Galerien field when no occasion is open', function () {
    expect(props('/')['galleries'])->toBeEmpty();
});

it('sums the pipeline from quotes still in play', function () {
    // Draft and sent are in play; accepted is won and superseded is gone.
    $project = Project::factory()->create();
    Quote::factory()->for($project)->create(['status' => QuoteStatus::Sent, 'total' => 4875000]);
    Quote::factory()->for($project)->create(['status' => QuoteStatus::Accepted, 'total' => 900000]);

    expect(props('/')['pipeline'])->toBe(4875000);
});

it('sums what is overdue and counts the instalments behind it', function () {
    // C-05 (#239): no metric without its proof. The dots are open against
    // settled, which is the only reading of "1 von 4 Raten offen" anyone can
    // check against the plan.
    $job = Job::factory()->create();
    $plan = PaymentPlan::factory()->for($job)->create(['total' => 380000]);
    PaymentInstallment::factory()->for($plan, 'plan')->create([
        'amount' => 290000,
        'due_on' => now()->subDays(5)->toDateString(),
    ]);
    PaymentInstallment::factory()->for($plan, 'plan')->create([
        'amount' => 90000,
        'due_on' => now()->addDays(9)->toDateString(),
    ]);

    $overdue = props('/')['overdue'];

    expect($overdue['total'])->toBe(290000)
        ->and($overdue['open'])->toBe(1)
        ->and($overdue['all'])->toBe(2)
        ->and($overdue['sinceDays'])->toBe(5);
});

it('reports no overdue day when nothing is late', function () {
    expect(props('/')['overdue'])->toBe(['total' => 0, 'open' => 0, 'all' => 0, 'sinceDays' => null]);
});

it('counts quotes sent and accepted over the trailing twelve months', function () {
    // Counts, not the percentage: the tile reads `3 von 8` and draws one dot
    // per quote, and a rounded percentage cannot be turned back into cases.
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
    // Older than the window; must not move either figure.
    Quote::factory()->for($project)->create([
        'status' => QuoteStatus::Accepted,
        'sent_at' => now()->subMonths(18),
        'accepted_at' => now()->subMonths(17),
    ]);

    expect(props('/')['quotes'])->toBe(['sent' => 8, 'accepted' => 3]);
});

it('shows no acceptance tile when nothing has been sent', function () {
    // Nothing sent is not "0 von 0" — the tile has no proof and does not
    // render, which is the rule the screen applies to every figure on it.
    expect(props('/')['quotes'])->toBe(['sent' => 0, 'accepted' => 0]);
});

// The dots under Pipeline: one per open lead, carrying its own stage so the
// screen can shade it. Drawn in stage order, or the colour distribution jumps
// the moment one case moves on.
it('sends the open leads\' stages in stage order', function () {
    leadAtStage(LeadStage::QuoteSent);
    leadAtStage(LeadStage::New);
    leadAtStage(LeadStage::Contacted);

    expect(props('/')['pipelineStages'])->toBe(['new', 'contacted', 'quote_sent']);
});

it('carries the newest inquiries with their occasion and source', function () {
    $lead = leadAtStage(LeadStage::New);

    $rows = props('/')['newLeads'];

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe($lead->project->public_id)
        ->and($rows[0]['type'])->toBe($lead->project->type->label());
});

// The card reads the primary shoot and the latest quote, which the panel's own
// query has to carry — otherwise the third inquiry costs two queries the first
// one did not.
it('reads three inquiries in the same number of queries as one', function () {
    leadAtStage(LeadStage::New);

    props('/');

    DB::enableQueryLog();
    props('/');
    $one = count(DB::getRawQueryLog());
    DB::flushQueryLog();

    leadAtStage(LeadStage::New);
    leadAtStage(LeadStage::Contacted);

    props('/');
    DB::flushQueryLog();
    props('/');
    $three = count(DB::getRawQueryLog());
    DB::disableQueryLog();

    expect($three)->toBe($one);
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

it('does not count a thread filed on the client\'s own last message', function () {
    // Thread::workState() calls this one erledigt: the studio decided the
    // conversation was finished on the very message it is filed against.
    $contact = Contact::factory()->create();
    $thread = Thread::factory()->for(Project::factory())->create();
    $message = Message::factory()->for($thread)->create(['contact_id' => $contact->id, 'author_id' => null]);
    $thread->forceFill(['closed_message_id' => $message->id])->save();

    expect(props('/')['unanswered'])->toBe(0);
});

it('does not count a thread with no messages at all', function () {
    // The obvious edge a naive "max(created_at) over this thread's messages"
    // formulation gets wrong: no message means no newest-message fact to
    // check, so the thread must not count as unanswered.
    Thread::factory()->for(Project::factory())->create();

    expect(props('/')['unanswered'])->toBe(0);
});
