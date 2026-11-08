<?php

use App\Automation\Actions\SweepDateOffsetAutomationRules;
use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Enums\AutomationRecipient;
use App\Automation\Models\AutomationRule;
use App\Automation\Notifications\AutomationReminder;
use App\Crm\Enums\LeadStage;
use App\Crm\Enums\ShootStatus;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Lead;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Gallery\Models\Gallery;
use App\Models\User;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

$today = fn () => CarbonImmutable::parse('2026-06-01', 'UTC');

function studioRule(AutomationAnchor $anchor, int $days = 3): AutomationRule
{
    return AutomationRule::factory()
        ->dateOffset($anchor, $days, AutomationOffsetDirection::Before)
        ->create(['recipient_type' => AutomationRecipient::Studio]);
}

function sweep(CarbonImmutable $today): int
{
    return app(SweepDateOffsetAutomationRules::class)($today);
}

// Die Vorankuendigung, die T-22s Verzug verhindert statt ihn zu reparieren.
it('announces an installment before it falls due', function () use ($today) {
    staffMember();

    $project = ClientCase::factory()->create();
    $plan = PaymentPlan::factory()->for(markBooked($project), 'case')->create();

    PaymentInstallment::factory()->create([
        'payment_plan_id' => $plan->id,
        'due_on' => $today()->addDays(3)->toDateString(),
    ]);
    PaymentInstallment::factory()->create([
        'payment_plan_id' => $plan->id,
        'due_on' => $today()->addDays(3)->toDateString(),
        'status' => PaymentInstallmentStatus::Paid,
    ]);

    studioRule(AutomationAnchor::InstallmentDue);

    expect(sweep($today()))->toBe(1);
});

it('warns before a gallery expires', function () use ($today) {
    staffMember();

    Gallery::factory()->create(['expires_at' => $today()->addDays(3)->toDateString()]);

    studioRule(AutomationAnchor::GalleryExpiry);

    expect(sweep($today()))->toBe(1);
});

it('counts from the day the primary shoot was delivered', function () use ($today) {
    staffMember();

    Shoot::factory()->primary()->atStatus(ShootStatus::Delivered)
        ->create(['status_entered_at' => $today()->subDays(14)]);
    Shoot::factory()->primary()->atStatus(ShootStatus::Editing)
        ->create(['status_entered_at' => $today()->subDays(14)]);
    Shoot::factory()->atStatus(ShootStatus::Delivered)
        ->create(['status_entered_at' => $today()->subDays(14)]);

    AutomationRule::factory()
        ->dateOffset(AutomationAnchor::Delivered, 14, AutomationOffsetDirection::After)
        ->create(['recipient_type' => AutomationRecipient::Studio]);

    expect(sweep($today()))->toBe(1);
});

it('counts from the day a still unanswered quote went out', function () use ($today) {
    staffMember();

    Quote::factory()->create(['status' => QuoteStatus::Sent, 'sent_at' => $today()->subDays(7)]);
    Quote::factory()->create(['status' => QuoteStatus::Accepted, 'sent_at' => $today()->subDays(7)]);

    AutomationRule::factory()
        ->dateOffset(AutomationAnchor::QuoteSent, 7, AutomationOffsetDirection::After)
        ->create(['recipient_type' => AutomationRecipient::Studio]);

    expect(sweep($today()))->toBe(1);
});

it('counts from the capture of an inquiry nobody has moved on', function () use ($today) {
    staffMember();

    Lead::factory()->atStage(LeadStage::New)->create(['captured_at' => $today()->subDays(2)]);
    Lead::factory()->atStage(LeadStage::Contacted)->create(['captured_at' => $today()->subDays(2)]);

    AutomationRule::factory()
        ->dateOffset(AutomationAnchor::InquiryCaptured, 2, AutomationOffsetDirection::After)
        ->create(['recipient_type' => AutomationRecipient::Studio]);

    expect(sweep($today()))->toBe(1);
});

it('follows up on a client task that is coming due', function () use ($today) {
    staffMember();

    Task::factory()->due($today()->addDays(3)->toDateString())->create(['audience' => TaskAudience::Client]);
    Task::factory()->due($today()->addDays(3)->toDateString())->create(['audience' => TaskAudience::Internal]);

    studioRule(AutomationAnchor::QuestionnaireDue);

    expect(sweep($today()))->toBe(1);
});

it('resolves an invoice field to nothing while the installment has no invoice', function () use ($today) {
    staffMember();

    $project = ClientCase::factory()->create();
    $plan = PaymentPlan::factory()->for(markBooked($project), 'case')->create();
    PaymentInstallment::factory()->create([
        'payment_plan_id' => $plan->id,
        'due_on' => $today()->addDays(3)->toDateString(),
    ]);
    studioRule(AutomationAnchor::InstallmentDue)->update(['notification_body' => 'Rechnung {{ invoice.number }}.']);

    sweep($today());

    Notification::assertSentTo(User::all(), AutomationReminder::class, fn ($n) => $n->body() === 'Rechnung .');
});
