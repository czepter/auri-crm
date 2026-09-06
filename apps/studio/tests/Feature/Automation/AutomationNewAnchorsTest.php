<?php

use App\Automation\Actions\SweepDateOffsetAutomationRules;
use App\Automation\Enums\AutomationAnchor;
use App\Automation\Enums\AutomationOffsetDirection;
use App\Automation\Enums\AutomationRecipient;
use App\Automation\Models\AutomationRule;
use App\Automation\Models\AutomationRun;
use App\Contracts\Models\Contract;
use App\Crm\Enums\TaskAudience;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Crm\Models\Task;
use App\Gallery\Models\Gallery;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
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

    $project = Project::factory()->create();
    $plan = PaymentPlan::factory()->create(['job_id' => Job::factory()->create(['project_id' => $project->id])]);

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

it('nudges before a contract deadline and leaves a signed one alone', function () use ($today) {
    staffMember();

    Contract::factory()->create(['sign_deadline_on' => $today()->addDays(3)->toDateString()]);

    studioRule(AutomationAnchor::ContractDeadline);

    expect(sweep($today()))->toBe(1)
        ->and(AutomationRun::query()->count())->toBe(1);
});

it('warns before a gallery expires', function () use ($today) {
    staffMember();

    Gallery::factory()->create(['expires_at' => $today()->addDays(3)->toDateString()]);

    studioRule(AutomationAnchor::GalleryExpiry);

    expect(sweep($today()))->toBe(1);
});

it('follows up on a client task that is coming due', function () use ($today) {
    staffMember();

    Task::factory()->due($today()->addDays(3)->toDateString())->create(['audience' => TaskAudience::Client]);
    Task::factory()->due($today()->addDays(3)->toDateString())->create(['audience' => TaskAudience::Internal]);

    studioRule(AutomationAnchor::QuestionnaireDue);

    expect(sweep($today()))->toBe(1);
});
