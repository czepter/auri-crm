<?php

use App\Crm\Actions\AdvanceLeadStage;
use App\Crm\Actions\RemindStalledLeads;
use App\Crm\Enums\LeadOutcome;
use App\Crm\Enums\LeadStage;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Lead;
use App\Crm\Models\Stage;
use App\Crm\Notifications\Studio\LeadStalled;
use App\Crm\Support\CrmVocabulary;
use App\Notifications\Enums\NotificationMode;
use App\Notifications\Support\NotificationPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('reports a lead that has sat past the first rung', function () {
    Notification::fake();
    $staff = staffMember();
    app(NotificationPreferences::class)->setMode($staff, LeadStalled::class, NotificationMode::Immediate);

    Lead::factory()->atStage(LeadStage::Contacted, 4)->create(['captured_by_id' => $staff->id]);

    expect(app(RemindStalledLeads::class)())->toBe(1);

    Notification::assertSentTo($staff, LeadStalled::class);
});

it('says nothing before the first rung', function () {
    Notification::fake();
    staffMember();

    Lead::factory()->atStage(LeadStage::Contacted, 2)->create();

    expect(app(RemindStalledLeads::class)())->toBe(0);

    Notification::assertNothingSent();
});

it('reports each rung once', function () {
    Notification::fake();
    staffMember();

    $lead = Lead::factory()->atStage(LeadStage::Contacted, 4)->create();

    app(RemindStalledLeads::class)();

    expect($lead->fresh()->stalled_reminders_sent)->toBe(1)
        ->and(app(RemindStalledLeads::class)())->toBe(0);
});

it('leaves a closed inquiry out whatever its outcome', function () {
    Notification::fake();
    staffMember();

    Lead::factory()->atStage(LeadStage::Contacted, 30)
        ->lost('Termin belegt', LeadOutcome::Declined)
        ->create();

    expect(app(RemindStalledLeads::class)())->toBe(0);
});

// Eine abgeschaffte Stufe mahnt nicht weiter.
it('leaves a lead in a deactivated stage out', function () {
    Notification::fake();
    staffMember();

    Lead::factory()->atStage(LeadStage::Contacted, 30)->create();

    Stage::query()->pipeline(StagePipeline::Lead)
        ->where('key', LeadStage::Contacted->value)
        ->update(['is_active' => false]);
    app(CrmVocabulary::class)->forget();

    expect(app(RemindStalledLeads::class)())->toBe(0);
});

it('starts the ladder over when the lead moves', function () {
    $lead = Lead::factory()->atStage(LeadStage::Contacted, 30)->create();
    $lead->forceFill(['stalled_reminders_sent' => 4])->save();

    $next = app(CrmVocabulary::class)->byKey(StagePipeline::Lead, LeadStage::QuoteSent->value);
    app(AdvanceLeadStage::class)($lead, $next, staffMember());

    expect($lead->fresh()->stalled_reminders_sent)->toBe(0);
});

it('closes an inquiry from the board with its outcome', function () {
    $lead = Lead::factory()->atStage(LeadStage::Contacted, 2)->create();

    $this->actingAs(staffMember(), 'staff')
        ->post("/anfragen/{$lead->case->public_id}/schliessen", [
            'outcome' => LeadOutcome::Declined->value,
            'reason' => 'Termin belegt',
        ])
        ->assertRedirect('/anfragen');

    expect($lead->case->fresh()->close_outcome)->toBe(LeadOutcome::Declined)
        ->and($lead->fresh()->isLost())->toBeTrue();
});

it('reopens an inquiry closed by mistake, from its own screen', function () {
    $lead = Lead::factory()->atStage(LeadStage::Contacted, 2)->create();
    $staff = staffMember();

    $this->actingAs($staff, 'staff')
        ->post("/anfragen/{$lead->case->public_id}/schliessen", [
            'outcome' => LeadOutcome::Declined->value,
            'reason' => 'Termin belegt',
        ]);

    $this->actingAs($staff, 'staff')
        ->post("/anfragen/{$lead->case->public_id}/wieder-oeffnen")
        ->assertRedirect();

    expect($lead->fresh()->isLost())->toBeFalse()
        ->and($lead->case->fresh()->close_outcome)->toBeNull()
        ->and($lead->fresh()->stage->key)->toBe(LeadStage::Contacted->value);
});
