<?php

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Crm\Models\Job;
use App\Crm\Models\Project;
use App\Enums\StaffRole;
use App\Models\User;
use App\Payments\Actions\ReadPaymentColumn;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::factory()->withMfa()->create();
    $this->actingAs($this->staff, 'staff');
});

function overdueInstallment(string $name, ?int $notifiedDaysAgo, int $amount = 110000): PaymentInstallment
{
    $project = Project::factory()->create(['name' => $name]);
    $project->attachContact(
        Contact::factory()->create(['phone' => '+49 40 1234567']),
        ProjectContactRole::Primary,
    );
    $job = Job::factory()->for($project)->create();
    $plan = PaymentPlan::factory()->for($job)->create(['total' => $amount]);

    return PaymentInstallment::factory()->for($plan, 'plan')->create([
        'amount' => $amount,
        'due_on' => now()->subDays(20)->toDateString(),
        'overdue_notified_at' => $notifiedDaysAgo === null ? null : now()->subDays($notifiedDaysAgo),
    ]);
}

it('does not row an overdue installment the scan has not yet noticed', function () {
    // No overdue_notified_at means DetectOverdueInstallments has not run
    // against it. The machine has not had its turn.
    overdueInstallment('Sievers–Brandt', notifiedDaysAgo: null);

    expect(app(ReadPaymentColumn::class)())->toBeEmpty();
});

it('does not row an installment dunned only yesterday', function () {
    // Stripe is still working. Three days is when a call stops being a
    // duplicate of what the machine is already doing.
    overdueInstallment('Sievers–Brandt', notifiedDaysAgo: 1);

    expect(app(ReadPaymentColumn::class)())->toBeEmpty();
});

it('rows an installment whose dunning has run out of road', function () {
    overdueInstallment('Sievers–Brandt', notifiedDaysAgo: 9);

    $rows = app(ReadPaymentColumn::class)();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->who)->toBe('Sievers–Brandt')
        ->and($rows->first()->what)->toBe('Stripe mahnt')
        ->and($rows->first()->emphasis)->toBe('seit 9 Tagen')
        ->and($rows->first()->whatTail)->toContain('1.100,00')
        ->and($rows->first()->hot)->toBeTrue()
        ->and($rows->first()->action->kind)->toBe('tel');
});

it('does not row an installment that has since been paid', function () {
    $installment = overdueInstallment('Sievers–Brandt', notifiedDaysAgo: 9);
    $installment->update(['status' => PaymentInstallmentStatus::Paid]);

    expect(app(ReadPaymentColumn::class)())->toBeEmpty();
});

it('shows a crew member only their own projects', function () {
    overdueInstallment('Nicht meins', notifiedDaysAgo: 9);

    $crew = User::factory()->withMfa()->create(['role' => StaffRole::SecondShooter]);

    expect(app(ReadPaymentColumn::class)($crew))->toBeEmpty();
});
