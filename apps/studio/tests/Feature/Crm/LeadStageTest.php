<?php

use App\Crm\Actions\AdvanceLeadStage;
use App\Crm\Actions\BookProject;
use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ProjectContactRole;
use App\Crm\Events\LeadLost;
use App\Crm\Events\LeadStageChanged;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Lead;
use App\Crm\Models\Shoot;
use App\Models\AuditLog;
use App\Models\User;
use App\Payments\Models\PaymentPlan;
use App\Quotes\Actions\AcceptQuote;
use App\Quotes\Actions\MarkQuoteSent;
use App\Quotes\Models\Quote;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('advances a lead and restamps the stage entry time', function () {
    $lead = Lead::factory()->atStage(LeadStage::New, daysAgo: 9)->create();

    $advanced = app(AdvanceLeadStage::class)($lead, stage(LeadStage::Contacted));

    expect($advanced->stage->key)->toBe(LeadStage::Contacted->value)
        ->and($advanced->stage_entered_at->isToday())->toBeTrue();
});

it('emits an event carrying both stages', function () {
    Event::fake([LeadStageChanged::class]);
    $lead = Lead::factory()->create();

    app(AdvanceLeadStage::class)($lead, stage(LeadStage::QuoteSent));

    Event::assertDispatched(LeadStageChanged::class, fn ($e) => $e->from->key === LeadStage::New->value
        && $e->to->key === LeadStage::QuoteSent->value
        && $e->lead->is($lead));
});

it('writes an audit row naming the transition', function () {
    $actor = User::factory()->create();
    $lead = Lead::factory()->create();

    app(AdvanceLeadStage::class)($lead, stage(LeadStage::Contacted), $actor);

    $row = AuditLog::where('event', 'lead.stage_changed')->sole();

    expect($row->actor_id)->toBe($actor->id)
        ->and($row->subject)->toBe($lead->public_id)
        ->and($row->context['from'])->toBe('new')
        ->and($row->context['to'])->toBe('contacted');
});

it('moves a lead backward, recording the direction in the audit row', function () {
    $lead = Lead::factory()->atStage(LeadStage::QuoteSent)->create();

    app(AdvanceLeadStage::class)($lead, stage(LeadStage::Contacted));

    // The from/to pair is what makes a correction readable as one, which is
    // why the backward move needs no event of its own.
    $row = AuditLog::where('event', 'lead.stage_changed')->sole();

    expect($lead->fresh()->stage->key)->toBe(LeadStage::Contacted->value)
        ->and($row->context['from'])->toBe('quote_sent')
        ->and($row->context['to'])->toBe('contacted');
});

it('refuses a transition to the stage the lead is already in', function () {
    $lead = Lead::factory()->atStage(LeadStage::QuoteSent)->create();

    app(AdvanceLeadStage::class)($lead, stage(LeadStage::QuoteSent));
})->throws(DomainException::class);

it('refuses to advance a lost lead', function () {
    $lead = Lead::factory()->lost('Budget')->create();

    app(AdvanceLeadStage::class)($lead, stage(LeadStage::Contacted));
})->throws(DomainException::class);

it('leaves nothing behind when a transition is refused', function () {
    $lead = Lead::factory()->atStage(LeadStage::QuoteSent)->create();

    try {
        app(AdvanceLeadStage::class)($lead, stage(LeadStage::QuoteSent));
    } catch (DomainException) {
        // expected
    }

    expect($lead->fresh()->stage->key)->toBe(LeadStage::QuoteSent->value)
        ->and(AuditLog::where('event', 'lead.stage_changed')->count())->toBe(0);
});

it('marks a lead lost from any stage without changing the stage', function () {
    Event::fake([LeadLost::class]);
    $lead = Lead::factory()->atStage(LeadStage::ContractSent)->create();

    $lost = app(AdvanceLeadStage::class)->markLost($lead, 'Anderer Fotograf');

    expect($lost->isLost())->toBeTrue()
        ->and($lost->stage->key)->toBe(LeadStage::ContractSent->value)
        ->and($lost->lost_reason)->toBe('Anderer Fotograf');

    Event::assertDispatched(LeadLost::class);
});

it('records the stage a lead was lost in on the audit row', function () {
    $lead = Lead::factory()->atStage(LeadStage::QuoteSent)->create();

    app(AdvanceLeadStage::class)->markLost($lead, 'Budget');

    expect(AuditLog::where('event', 'lead.lost')->sole()->context['stage'])->toBe('quote_sent');
});

it('refuses to lose a lead twice', function () {
    $lead = Lead::factory()->lost('Budget')->create();

    app(AdvanceLeadStage::class)->markLost($lead, 'Nochmal');
})->throws(DomainException::class);

// "Gebucht" is the booking, not a label for one: without the job the record
// stands in neither list — /anfragen leaves booked ones out, /projekte reads
// the job.
it('books the project when the lead reaches gebucht', function () {
    $lead = Lead::factory()->atStage(LeadStage::ContractSent)->create();

    app(AdvanceLeadStage::class)($lead, stage(LeadStage::Booked), User::factory()->create());

    expect($lead->project->job()->exists())->toBeTrue();
});

it('backdates the booking and its shoots with the stage move', function () {
    $lead = Lead::factory()->atStage(LeadStage::ContractSent)->create();
    $shoot = Shoot::factory()->for($lead->project)->create();

    app(AdvanceLeadStage::class)(
        $lead,
        stage(LeadStage::Booked),
        User::factory()->create(),
        CarbonImmutable::parse('2026-05-02 10:00'),
    );

    expect($lead->project->job->booked_at->toDateString())->toBe('2026-05-02')
        ->and($shoot->fresh()->status_entered_at->toDateString())->toBe('2026-05-02');
});

it('leaves an existing job alone when the stage catches up', function () {
    $lead = Lead::factory()->atStage(LeadStage::ContractSent)->create();
    $job = Job::factory()->for($lead->project)->create();

    app(AdvanceLeadStage::class)($lead, stage(LeadStage::Booked), User::factory()->create());

    expect($lead->project->job()->count())->toBe(1)
        ->and($lead->project->job->is($job))->toBeTrue();
});

it('restores the original entry time when a mistaken move is taken back', function () {
    $lead = Lead::factory()->atStage(LeadStage::New, daysAgo: 9)->create();
    $entered = $lead->stage_entered_at;

    app(AdvanceLeadStage::class)($lead, stage(LeadStage::Contacted));
    app(AdvanceLeadStage::class)($lead, stage(LeadStage::New));

    // Back in Neu it has stood there since it arrived, not since the undo —
    // the board colours staleness off this, and T-20's reminder ladder counts
    // its rungs off it.
    expect($lead->fresh()->stage->key)->toBe(LeadStage::New->value)
        ->and($lead->fresh()->stage_entered_at->diffInSeconds($entered))->toBeLessThan(2);
});

it('backdates a move that is entered after the fact', function () {
    $lead = Lead::factory()->create();

    app(AdvanceLeadStage::class)($lead, stage(LeadStage::Contacted), null, CarbonImmutable::parse('2026-07-03 09:00'));

    expect($lead->fresh()->stage_entered_at->toDateString())->toBe('2026-07-03');
});

it('takes the booking back when the won stage is taken back', function () {
    $lead = Lead::factory()->create();
    $actor = User::factory()->create();

    app(AdvanceLeadStage::class)($lead, stage(LeadStage::Booked), $actor);
    expect(Job::count())->toBe(1);

    app(AdvanceLeadStage::class)($lead, stage(LeadStage::ContractSent), $actor);

    expect(Job::count())->toBe(0)
        ->and($lead->fresh()->stage->key)->toBe(LeadStage::ContractSent->value)
        ->and(AuditLog::where('event', 'project.unbooked')->count())->toBe(1);
});

it('dates a backdated unbooking when it happened', function () {
    $lead = Lead::factory()->atStage(LeadStage::ContractSent)->create();
    Shoot::factory()->for($lead->project)->create();
    $actor = User::factory()->create();

    app(AdvanceLeadStage::class)($lead, stage(LeadStage::Booked), $actor);
    app(AdvanceLeadStage::class)($lead->fresh(), stage(LeadStage::ContractSent), $actor, CarbonImmutable::parse('2026-05-02 10:00'));

    $dates = AuditLog::whereIn('event', ['project.unbooked', 'shoot.status_changed'])
        ->where('context->reverted', true)
        ->get()
        ->map(fn (AuditLog $log) => substr($log->context['occurred_at'] ?? '', 0, 10))
        ->unique()
        ->values()
        ->all();

    expect($dates)->toBe(['2026-05-02']);
});

it('refuses to take the booking back once a payment plan hangs off it', function () {
    $lead = Lead::factory()->create();
    $actor = User::factory()->create();

    app(AdvanceLeadStage::class)($lead, stage(LeadStage::Booked), $actor);
    PaymentPlan::factory()->for(Job::sole())->create();

    expect(fn () => app(AdvanceLeadStage::class)($lead, stage(LeadStage::ContractSent), $actor))
        ->toThrow(DomainException::class);

    expect(Job::count())->toBe(1)
        ->and($lead->fresh()->stage->key)->toBe(LeadStage::Booked->value);
});

it('leaves the stage alone when the booking fails', function () {
    $this->mock(BookProject::class)->shouldReceive('__invoke')->andThrow(new DomainException('Shoot'));
    $lead = Lead::factory()->atStage(LeadStage::ContractSent)->create();

    expect(fn () => app(AdvanceLeadStage::class)($lead, stage(LeadStage::Booked), User::factory()->create()))
        ->toThrow(DomainException::class);

    expect($lead->fresh()->stage->key)->toBe(LeadStage::ContractSent->value)
        ->and(AuditLog::where('event', 'lead.stage_changed')->exists())->toBeFalse();
});

it('follows the quote forward and leaves a lead further along alone', function () {
    Notification::fake();
    $lead = Lead::factory()->create();
    $lead->project->attachContact(Contact::factory()->create(), ProjectContactRole::Primary);
    $quote = Quote::factory()->create(['project_id' => $lead->project_id]);
    $ahead = Lead::factory()->atStage(LeadStage::Booked)->create();
    $later = Quote::factory()->create(['project_id' => $ahead->project_id]);

    app(MarkQuoteSent::class)($quote, 'self');
    expect($lead->fresh()->stage->key)->toBe(LeadStage::QuoteSent->value);

    app(AcceptQuote::class)($quote->fresh(), $lead->project->primaryContact(), null);
    app(MarkQuoteSent::class)($later, 'self');

    expect($lead->fresh()->stage->key)->toBe(LeadStage::QuoteAccepted->value)
        ->and($ahead->fresh()->stage->key)->toBe(LeadStage::Booked->value);
});

it('cancels the open shoots of a lost project and leaves an earlier absage alone', function () {
    $lead = Lead::factory()->create();
    $open = Shoot::factory()->for($lead->project)->create();
    $earlier = Shoot::factory()->for($lead->project)->create(['cancelled_at' => now()->subWeek(), 'cancelled_reason' => 'Regen']);

    app(AdvanceLeadStage::class)->markLost($lead, 'Budget', lostAt: CarbonImmutable::parse('2026-05-02 10:00'));

    expect($open->fresh()->cancelled_at->toDateString())->toBe('2026-05-02')
        ->and($open->fresh()->cancelled_reason)->toBe('Budget')
        ->and($earlier->fresh()->cancelled_reason)->toBe('Regen');

    app(AdvanceLeadStage::class)->reopen($lead);

    expect($open->fresh()->isCancelled())->toBeFalse()
        ->and($earlier->fresh()->isCancelled())->toBeTrue();
});

it('reopens a lead that was closed by mistake', function () {
    $lead = Lead::factory()->create();

    app(AdvanceLeadStage::class)->markLost($lead, 'Budget');
    expect($lead->fresh()->isLost())->toBeTrue();

    app(AdvanceLeadStage::class)->reopen($lead);

    expect($lead->fresh()->isLost())->toBeFalse()
        ->and($lead->fresh()->lost_reason)->toBeNull()
        ->and($lead->fresh()->outcome)->toBeNull();

    // And it can move again, which is the whole point.
    app(AdvanceLeadStage::class)($lead, stage(LeadStage::Contacted));
    expect($lead->fresh()->stage->key)->toBe(LeadStage::Contacted->value);
});
