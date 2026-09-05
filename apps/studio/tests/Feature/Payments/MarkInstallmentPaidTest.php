<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Payments\Actions\MarkInstallmentPaid;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Enums\SettledVia;
use App\Payments\Events\InstallmentPaid;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

// staffMember() lives in tests/Pest.php — see the note there.

// --- App\Payments\Actions\MarkInstallmentPaid -------------------------------

it('marks an installment paid with the date, method and reference given, and dispatches InstallmentPaid', function () {
    Event::fake([InstallmentPaid::class]);

    $installment = PaymentInstallment::factory()->create();

    $result = app(MarkInstallmentPaid::class)($installment, SettledVia::Transfer, '2026-09-01', 'RE-2026-1');

    expect($result->status)->toBe(PaymentInstallmentStatus::Paid)
        ->and($result->settled_via)->toBe(SettledVia::Transfer)
        ->and($result->paid_on->format('Y-m-d'))->toBe('2026-09-01')
        ->and($result->payment_reference)->toBe('RE-2026-1');

    Event::assertDispatched(InstallmentPaid::class, fn (InstallmentPaid $event) => $event->installment->is($installment));
});

it('fires the SAME InstallmentPaid event regardless of the settlement method — a transfer unlocks exactly what Stripe would', function (SettledVia $method) {
    Event::fake([InstallmentPaid::class]);

    $installment = PaymentInstallment::factory()->create();

    app(MarkInstallmentPaid::class)($installment, $method, '2026-09-01', null);

    Event::assertDispatched(InstallmentPaid::class);
})->with([SettledVia::Transfer, SettledVia::Manual, SettledVia::Stripe]);

it('is idempotent: marking an already-paid installment paid again writes nothing and dispatches nothing a second time', function () {
    Event::fake([InstallmentPaid::class]);

    $installment = PaymentInstallment::factory()->create();

    app(MarkInstallmentPaid::class)($installment, SettledVia::Transfer, '2026-09-01', 'RE-2026-1');
    $again = app(MarkInstallmentPaid::class)($installment, SettledVia::Manual, '2026-09-05', 'something-else');

    // The second call is a pure no-op: the original method, date and
    // reference from the first call survive untouched.
    expect($again->settled_via)->toBe(SettledVia::Transfer)
        ->and($again->paid_on->format('Y-m-d'))->toBe('2026-09-01')
        ->and($again->payment_reference)->toBe('RE-2026-1');

    Event::assertDispatchedTimes(InstallmentPaid::class, 1);
});

it('leaves an audit row naming the method, date and reference', function () {
    $actor = staffMember(StaffRole::Owner);
    $installment = PaymentInstallment::factory()->create();

    app(MarkInstallmentPaid::class)($installment, SettledVia::Transfer, '2026-09-01', 'RE-2026-1', $actor);

    $row = AuditLog::where('event', 'payment_installment.paid')->where('subject', $installment->public_id)->sole();
    expect($row->actor_id)->toBe($actor->id)
        ->and($row->context['method'])->toBe('transfer')
        ->and($row->context['reference'])->toBe('RE-2026-1');
});

// --- The admin screen: the HTTP boundary, idempotency included -------------

function t07PaidPlan(): PaymentPlan
{
    $plan = PaymentPlan::factory()->create(['total' => 100000]);
    PaymentInstallment::factory()->for($plan, 'plan')->create(['amount' => 100000, 'sort_order' => 0]);

    return $plan->fresh('installments');
}

it('marks an installment paid through the admin screen', function () {
    $plan = t07PaidPlan();
    $installment = $plan->installments->sole();
    $user = staffMember(StaffRole::Owner);

    $this->actingAs($user, 'staff')
        ->post("/raten/{$installment->public_id}/bezahlt", [
            'paid_on' => '2026-09-01',
            'method' => 'transfer',
            'reference' => $plan->reference,
        ])
        ->assertRedirect();

    $fresh = $installment->fresh();
    expect($fresh->status)->toBe(PaymentInstallmentStatus::Paid)
        ->and($fresh->settled_via)->toBe(SettledVia::Transfer);
});

it('emits exactly one InstallmentPaid event when the same mark-paid request is submitted twice', function () {
    Event::fake([InstallmentPaid::class]);

    $plan = t07PaidPlan();
    $installment = $plan->installments->sole();
    $user = staffMember(StaffRole::Owner);

    $payload = ['paid_on' => '2026-09-01', 'method' => 'transfer', 'reference' => $plan->reference];

    $this->actingAs($user, 'staff')->post("/raten/{$installment->public_id}/bezahlt", $payload)->assertRedirect();
    $this->actingAs($user, 'staff')->post("/raten/{$installment->public_id}/bezahlt", $payload)->assertRedirect();

    Event::assertDispatchedTimes(InstallmentPaid::class, 1);
});

it('refuses a role without ManageContracts, and lets one with it through', function (StaffRole $role, int $status) {
    $plan = t07PaidPlan();
    $installment = $plan->installments->sole();

    $this->actingAs(staffMember($role), 'staff')
        ->post("/raten/{$installment->public_id}/bezahlt", [
            'paid_on' => '2026-09-01',
            'method' => 'transfer',
        ])
        ->assertStatus($status);
})->with([
    [StaffRole::Owner, 302],
    [StaffRole::Photographer, 302],
    [StaffRole::Editor, 302],
    [StaffRole::SecondShooter, 403],
    [StaffRole::Assistant, 403],
]);

it('rejects an unknown settlement method', function () {
    $plan = t07PaidPlan();
    $installment = $plan->installments->sole();
    $user = staffMember(StaffRole::Owner);

    $this->actingAs($user, 'staff')
        ->from('/auftraege/xyz')
        ->post("/raten/{$installment->public_id}/bezahlt", [
            'paid_on' => '2026-09-01',
            'method' => 'bitcoin',
        ])
        ->assertSessionHasErrors('method');
});
