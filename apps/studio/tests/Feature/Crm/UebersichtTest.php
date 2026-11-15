<?php

use App\Crm\Enums\BookedVia;
use App\Crm\Enums\CaseContactRole;
use App\Crm\Enums\LeadOutcome;
use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ShootStatus;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Crm\Models\CrewAssignment;
use App\Crm\Models\Lead;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Enums\StaffRole;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryView;
use App\Models\Absence;
use App\Models\User;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

function bookedForDashboard(string $name = 'Meier & Kowalski'): ClientCase
{
    $project = ClientCase::factory()->create(['name' => $name]);
    markBooked($project);

    return $project;
}

it('lists upcoming shooting days in time order, with no week window', function () {
    $project = bookedForDashboard();
    Shoot::factory()->for($project, 'case')->on(now()->addDays(20)->format('Y-m-d'))->create(['call_time' => '14:00', 'venue_name' => 'Gut Basthorst', 'label' => 'Preshoot']);
    Shoot::factory()->for($project, 'case')->on(now()->addDays(2)->format('Y-m-d'))->create(['call_time' => '09:00']);

    $entries = props('/')['entries'];

    expect(collect($entries)->map(fn ($entry) => $entry['date'].' '.$entry['time'])->all())->toBe([
        now()->addDays(2)->format('Y-m-d').' 09:00',
        now()->addDays(20)->format('Y-m-d').' 14:00',
    ])
        ->and($entries[1]['where'])->toBe('Gut Basthorst')
        ->and($entries[1]['what'])->toBe('Preshoot · Meier & Kowalski')
        ->and($entries[0]['href'])->toBe('/auftraege/'.$project->public_id.'/shootings');
});

it('leaves out what is past, cancelled, or unbooked', function () {
    $booked = bookedForDashboard();
    Shoot::factory()->for($booked, 'case')->on(now()->subDay()->format('Y-m-d'))->create();
    Shoot::factory()->for($booked, 'case')->on(now()->addDays(4)->format('Y-m-d'))->cancelled('Abgesagt')->create();
    Shoot::factory()->for($booked, 'case')->on(now()->addDays(3)->format('Y-m-d'))->create();

    Shoot::factory()->for(ClientCase::factory()->create(), 'case')->on(now()->addDays(5)->format('Y-m-d'))->create();

    expect(props('/')['entries'])->toHaveCount(1);
});

// An absence is an entry on the day it costs, dimmed — not a sentence above the list.
it('puts an absence among the dates as its own entry', function () {
    Absence::factory()->create([
        'user_id' => User::factory()->create(['name' => 'Nina Brandt'])->id,
        'starts_on' => now()->addDays(2)->toDateString(),
        'ends_on' => now()->addDays(3)->toDateString(),
    ]);

    $entries = props('/')['entries'];

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['what'])->toBe('Nina Brandt')
        ->and($entries[0]['away'])->toBeTrue()
        ->and($entries[0]['href'])->toBeNull();
});

it('lists what is owed, overdue first by date', function () {
    Task::factory()->due(now()->subDays(2)->format('Y-m-d'))->create(['title' => 'Spät']);
    Task::factory()->due(now()->addDays(9)->format('Y-m-d'))->create(['title' => 'Später']);
    Task::factory()->done()->create(['title' => 'Erledigt']);

    $tasks = props('/')['tasks'];

    expect(collect($tasks)->pluck('title')->all())->toBe(['Spät', 'Später'])
        ->and($tasks[0]['isOverdue'])->toBeTrue()
        ->and($tasks[1]['isOverdue'])->toBeFalse();
});

it('caps the tasks at four, not at one row per project', function () {
    $loud = ClientCase::factory()->create(['name' => 'Laut']);
    Task::factory()->for($loud, 'case')->count(9)->due(now()->subDays(3)->format('Y-m-d'))->create();

    $quiet = ClientCase::factory()->create(['name' => 'Leise']);
    Task::factory()->for($quiet, 'case')->due(now()->subDay()->format('Y-m-d'))->create();

    $props = props('/');

    expect(collect($props['tasks'])->pluck('projectName')->all())->toBe(array_fill(0, 4, 'Laut'))
        ->and($props['openTasks'])->toBe(10);
});

it('does not call a task due today overdue', function () {
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

it('marks a task done on the day it was done, and corrects that day without reopening it', function () {
    $task = Task::factory()->create();

    $this->post("/aufgaben/{$task->public_id}/erledigt", ['completed_on' => '2026-08-21'])->assertRedirect();
    expect($task->fresh()->completed_at->toDateString())->toBe('2026-08-21');

    $this->post("/aufgaben/{$task->public_id}/erledigt", ['completed_on' => '2026-08-24'])->assertRedirect();
    expect($task->fresh()->isDone())->toBeTrue()
        ->and($task->fresh()->completed_at->toDateString())->toBe('2026-08-24');

    $this->post("/aufgaben/{$task->public_id}/erledigt", ['completed_on' => now()->addDay()->toDateString()])
        ->assertSessionHasErrors('completed_on');
});

it('never puts an integer identifier into the übersicht', function () {
    $project = bookedForDashboard();
    $shoot = Shoot::factory()->for($project, 'case')->on(now()->addDay()->format('Y-m-d'))->create();
    CrewAssignment::factory()->for($shoot)->create();
    Task::factory()->for($project, 'case')->create(['shoot_id' => $shoot->id, 'audience' => TaskAudience::Client]);
    Quote::factory()->for($project, 'case')->create(['status' => QuoteStatus::Sent, 'sent_at' => now()->subDays(20), 'total' => 340_000]);
    AuditLogger::record(event: 'quote.sent', subject: 'q', projectId: $project->id);

    expect(integerIdPaths(props('/')))->toBe([]);
});

it('carries the rows owed on payments under Zahlungen', function () {
    $project = ClientCase::factory()->create(['name' => 'Duhnkrack & W.']);
    $project->attachContact(Contact::factory()->create(['phone' => '+4940123']), CaseContactRole::Primary);
    markBooked($project);
    $plan = PaymentPlan::factory()->for($project, 'case')->create(['total' => 110000]);
    PaymentInstallment::factory()->for($plan, 'plan')->create([
        'amount' => 110000,
        'due_on' => now()->subDays(20)->toDateString(),
        'overdue_notified_at' => now()->subDays(9),
    ]);

    $dunning = props('/')['dunning'];

    expect($dunning)->toHaveCount(1)
        ->and(collect($dunning)->pluck('projectId')->unique()->all())->toBe([$project->public_id]);
});

it('shows nothing to do when nothing is owed', function () {
    $props = props('/');

    expect($props['todo'])->toBeEmpty()
        ->and($props['tasks'])->toBeEmpty();
});

it('puts the open inquiries and projects on the Übersicht', function () {
    $inquiry = Lead::factory()->for(ClientCase::factory(), 'case')->atStage(LeadStage::New, 5)->create();

    $project = bookedForDashboard();
    Shoot::factory()->for($project, 'case')->primary()->atStatus(ShootStatus::Editing)->on(now()->subDays(20)->toDateString())->create();

    $props = props('/');
    $ids = fn (array $groups) => collect($groups)->flatMap(fn (array $group) => array_column($group['rows'], 'id'))->all();

    expect($ids($props['leads']))->toBe([$inquiry->case->public_id])
        ->and($ids($props['projects']))->toBe([$project->public_id]);
});

it('carries the Galerien field', function () {
    $project = bookedForDashboard();
    Shoot::factory()->for($project, 'case')->atStatus(ShootStatus::Delivered)->create();
    Gallery::factory()->for($project, 'case')->create(['expires_at' => now()->addDays(12)]);

    expect(props('/')['galleries'])->toHaveCount(1);
});

it('sums the pipeline from quotes still in play', function () {
    $project = ClientCase::factory()->create();
    Quote::factory()->for($project, 'case')->create(['status' => QuoteStatus::Sent, 'net' => 4875000, 'vat_mode' => 'small_business']);
    $won = ClientCase::factory()->create();
    Quote::factory()->for($won, 'case')->create(['status' => QuoteStatus::Accepted, 'net' => 900000, 'vat_mode' => 'small_business']);

    expect(props('/')['pipeline'])->toBe(['total' => 4875000, 'priorYear' => null]);
});

it('counts one quote per case in the pipeline', function () {
    $case = ClientCase::factory()->create();
    Quote::factory()->for($case, 'case')->create(['status' => QuoteStatus::Sent, 'net' => 100000, 'vat_mode' => 'small_business']);
    Quote::factory()->for($case, 'case')->create(['status' => QuoteStatus::Draft, 'net' => 100000, 'vat_mode' => 'small_business']);

    expect(props('/')['pipeline']['total'])->toBe(100000);
});

it('leaves booked and closed cases out of the pipeline', function () {
    $open = ClientCase::factory()->create();
    Quote::factory()->for($open, 'case')->create(['status' => QuoteStatus::Draft, 'net' => 59000, 'vat_mode' => 'small_business']);
    $booked = ClientCase::factory()->create(['booked_at' => now(), 'booked_via' => BookedVia::Manual]);
    Quote::factory()->for($booked, 'case')->create(['status' => QuoteStatus::Sent, 'net' => 120000, 'vat_mode' => 'small_business']);
    $closed = ClientCase::factory()->create(['closed_at' => now(), 'close_reason' => 'Budget', 'close_outcome' => LeadOutcome::Lost]);
    Quote::factory()->for($closed, 'case')->create(['status' => QuoteStatus::Sent, 'net' => 146000, 'vat_mode' => 'small_business']);

    expect(props('/')['pipeline']['total'])->toBe(59000);
});

it('shows the running balance and the next instalment when nothing is late', function () {
    $plan = PaymentPlan::factory()->create(['total' => 380000]);
    PaymentInstallment::factory()->for($plan, 'plan')->create(['amount' => 90000, 'due_on' => now()->addDays(9)->toDateString()]);
    PaymentInstallment::factory()->for($plan, 'plan')->create(['amount' => 290000, 'due_on' => now()->addDays(40)->toDateString()]);
    PaymentInstallment::factory()->for($plan, 'plan')->paid()->create(['amount' => 50000, 'due_on' => now()->subDays(3)->toDateString()]);

    $payments = props('/')['payments'];

    expect($payments['open'])->toBe(380000)
        ->and($payments['overdue'])->toBe(0)
        ->and($payments['nextDueOn'])->toBe(now()->addDays(9)->toDateString())
        ->and($payments['nextAmount'])->toBe(90000)
        ->and($payments['instalments'])->toBe(3);
});

it('sums what is overdue and counts the instalments behind it', function () {
    $plan = PaymentPlan::factory()->create(['total' => 380000]);
    PaymentInstallment::factory()->for($plan, 'plan')->create(['amount' => 290000, 'due_on' => now()->subDays(5)->toDateString()]);
    PaymentInstallment::factory()->for($plan, 'plan')->create(['amount' => 90000, 'due_on' => now()->addDays(9)->toDateString()]);

    $payments = props('/')['payments'];

    expect($payments['overdue'])->toBe(290000)
        ->and($payments['overdueInstalments'])->toBe(1)
        ->and($payments['instalments'])->toBe(2)
        ->and($payments['overdueSinceDays'])->toBe(5);
});

it('reports nothing owed when there is no plan', function () {
    expect(props('/')['payments'])->toBe([
        'open' => 0,
        'overdue' => 0,
        'overdueInstalments' => 0,
        'instalments' => 0,
        'overdueSinceDays' => null,
        'nextDueOn' => null,
        'nextAmount' => null,
    ]);
});

it('counts quotes over the chosen range, with the same range a year earlier beside them', function () {
    Lead::factory()->count(5)->create(['captured_at' => now()->subMonths(3), 'stage' => LeadStage::QuoteSent]);
    Lead::factory()->count(3)->create(['captured_at' => now()->subMonths(3), 'stage' => LeadStage::QuoteAccepted]);
    Lead::factory()->create(['captured_at' => now()->subMonths(18), 'stage' => LeadStage::QuoteAccepted]);

    expect(props('/?zeitraum=year')['quotes'])->toBe(['sent' => 8, 'accepted' => 3, 'priorSent' => 1, 'priorAccepted' => 1])
        ->and(props('/?zeitraum=30')['quotes'])->toBe(['sent' => 0, 'accepted' => 0, 'priorSent' => null, 'priorAccepted' => null]);
});

it('falls back to thirty days for a range it does not know', function () {
    expect(props('/?zeitraum=kaputt')['range'])->toBe('30');
});

it('lists counting quote openings in the activity and skips the others', function () {
    $project = ClientCase::factory()->create(['name' => 'Ahrens']);
    $quote = Quote::factory()->for($project, 'case')->create();
    $quote->views()->create(['via' => 'link', 'viewed_at' => now()->subHour(), 'counts' => false]);
    $quote->views()->create(['via' => 'link', 'viewed_at' => now()->subMinutes(5)]);

    $activity = props('/')['activity'];

    expect(collect($activity)->pluck('text')->all())->toBe(['Ahrens · Angebot geöffnet'])
        ->and($activity[0]['href'])->toBe('/auftraege/'.$project->public_id.'/angebote');
});

it('counts booked shooting days in the range and a year earlier', function () {
    $project = bookedForDashboard();
    Shoot::factory()->for($project, 'case')->on(now()->subDays(10)->format('Y-m-d'))->create();
    Shoot::factory()->for($project, 'case')->on(now()->subDays(12)->format('Y-m-d'))->cancelled('Abgesagt')->create();
    Shoot::factory()->for($project, 'case')->on(now()->subYear()->subDays(10)->format('Y-m-d'))->create();
    Shoot::factory()->for(ClientCase::factory()->create(), 'case')->on(now()->subDays(5)->format('Y-m-d'))->create();

    expect(props('/?zeitraum=30')['shoots'])->toBe(['count' => 1, 'priorYear' => 1]);
});

it('tells what moved across projects, from the history allow-list and gallery openings', function () {
    $project = ClientCase::factory()->create(['name' => 'Ahrens']);
    AuditLogger::record(event: 'payment_installment.paid', projectId: $project->id);
    AuditLogger::record(event: 'quote.updated', projectId: $project->id);

    $gallery = Gallery::factory()->for($project, 'case')->create();
    foreach (['a', 'b'] as $visitor) {
        GalleryView::create(['gallery_id' => $gallery->id, 'visitor_identifier' => $visitor, 'viewed_on' => now()->subDay()->toDateString()]);
    }

    $activity = props('/')['activity'];

    expect(collect($activity)->pluck('text')->all())->toBe(['Ahrens · Zahlung erhalten', 'Ahrens · Galerie geöffnet'])
        ->and(collect($activity)->pluck('href')->all())->toBe([
            '/auftraege/'.$project->public_id.'/angebote',
            '/auftraege/'.$project->public_id.'/galerien',
        ])
        ->and($activity[0]['fresh'])->toBeTrue()
        ->and($activity[1]['when'])->toBe('gestern');
});

it('counts only what the viewer may open', function () {
    $hidden = ClientCase::factory()->create();
    markBooked($hidden);
    Quote::factory()->for($hidden, 'case')->create(['status' => QuoteStatus::Sent, 'total' => 250_00]);
    AuditLogger::record(event: 'payment_installment.paid', projectId: $hidden->id);

    $this->actingAs(staffMember(StaffRole::Contributor), 'staff');

    $props = props('/');

    expect($props['pipeline']['total'])->toBe(0)
        ->and($props['activity'])->toBeEmpty()
        ->and(collect($props['projects'])->pluck('rows')->flatten()->all())->toBeEmpty();
});
