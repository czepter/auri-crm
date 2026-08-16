<?php

use App\Crm\Actions\AdvanceLeadStage;
use App\Crm\Enums\LeadStage;
use App\Crm\Events\LeadLost;
use App\Crm\Events\LeadStageChanged;
use App\Crm\Models\Lead;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('advances a lead and restamps the stage entry time', function () {
    $lead = Lead::factory()->atStage(LeadStage::New, daysAgo: 9)->create();

    $advanced = app(AdvanceLeadStage::class)($lead, LeadStage::Contacted);

    expect($advanced->stage)->toBe(LeadStage::Contacted)
        ->and($advanced->stage_entered_at->isToday())->toBeTrue();
});

it('emits an event carrying both stages', function () {
    Event::fake([LeadStageChanged::class]);
    $lead = Lead::factory()->create();

    app(AdvanceLeadStage::class)($lead, LeadStage::QuoteSent);

    Event::assertDispatched(LeadStageChanged::class, fn ($e) => $e->from === LeadStage::New
        && $e->to === LeadStage::QuoteSent
        && $e->lead->is($lead));
});

it('writes an audit row naming the transition', function () {
    $actor = User::factory()->create();
    $lead = Lead::factory()->create();

    app(AdvanceLeadStage::class)($lead, LeadStage::Contacted, $actor);

    $row = AuditLog::where('event', 'lead.stage_changed')->sole();

    expect($row->actor_id)->toBe($actor->id)
        ->and($row->subject)->toBe($lead->public_id)
        ->and($row->context['from'])->toBe('new')
        ->and($row->context['to'])->toBe('contacted');
});

it('moves a lead backward, recording the direction in the audit row', function () {
    $lead = Lead::factory()->atStage(LeadStage::QuoteSent)->create();

    app(AdvanceLeadStage::class)($lead, LeadStage::Contacted);

    // The from/to pair is what makes a correction readable as one, which is
    // why the backward move needs no event of its own.
    $row = AuditLog::where('event', 'lead.stage_changed')->sole();

    expect($lead->fresh()->stage)->toBe(LeadStage::Contacted)
        ->and($row->context['from'])->toBe('quote_sent')
        ->and($row->context['to'])->toBe('contacted');
});

it('refuses a transition to the stage the lead is already in', function () {
    $lead = Lead::factory()->atStage(LeadStage::QuoteSent)->create();

    app(AdvanceLeadStage::class)($lead, LeadStage::QuoteSent);
})->throws(DomainException::class);

it('refuses to advance a lost lead', function () {
    $lead = Lead::factory()->lost('Budget')->create();

    app(AdvanceLeadStage::class)($lead, LeadStage::Contacted);
})->throws(DomainException::class);

it('leaves nothing behind when a transition is refused', function () {
    $lead = Lead::factory()->atStage(LeadStage::QuoteSent)->create();

    try {
        app(AdvanceLeadStage::class)($lead, LeadStage::QuoteSent);
    } catch (DomainException) {
        // expected
    }

    expect($lead->fresh()->stage)->toBe(LeadStage::QuoteSent)
        ->and(AuditLog::where('event', 'lead.stage_changed')->count())->toBe(0);
});

it('marks a lead lost from any stage without changing the stage', function () {
    Event::fake([LeadLost::class]);
    $lead = Lead::factory()->atStage(LeadStage::ContractSent)->create();

    $lost = app(AdvanceLeadStage::class)->markLost($lead, 'Anderer Fotograf');

    expect($lost->isLost())->toBeTrue()
        ->and($lost->stage)->toBe(LeadStage::ContractSent)
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
