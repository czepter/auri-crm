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
use Illuminate\Support\Facades\Exceptions;

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

it('reports a payment for a rate cancelled after the snapshot was taken', function () {
    Exceptions::fake();
    Event::fake([InstallmentPaid::class]);

    $installment = PaymentInstallment::factory()->create();
    PaymentInstallment::whereKey($installment->id)->update(['status' => PaymentInstallmentStatus::Cancelled, 'cancelled_at' => now()]);

    app(MarkInstallmentPaid::class)($installment, SettledVia::Stripe, '2026-09-01', 'cs_test_1');

    expect($installment->fresh()->status)->toBe(PaymentInstallmentStatus::Cancelled)
        ->and($installment->fresh()->paid_amount)->toBe(0);
    Exceptions::assertReported(fn (RuntimeException $e) => str_contains($e->getMessage(), 'stornierte Rate'));
    Event::assertNotDispatched(InstallmentPaid::class);
});

it('leaves an audit row naming the method, date and reference', function () {
    $actor = staffMember(StaffRole::Admin);
    $installment = PaymentInstallment::factory()->create();

    app(MarkInstallmentPaid::class)($installment, SettledVia::Transfer, '2026-09-01', 'RE-2026-1', $actor);

    $row = AuditLog::where('event', 'payment_installment.paid')->where('subject', $installment->public_id)->sole();
    expect($row->actor_id)->toBe($actor->id)
        ->and($row->context['method'])->toBe('transfer')
        ->and($row->context['reference'])->toBe('RE-2026-1');
});

// --- #414: Teilzahlung -----------------------------------------------------

it('books a short payment as partial and leaves the remainder open', function () {
    Event::fake([InstallmentPaid::class]);

    $installment = PaymentInstallment::factory()->create(['amount' => 100000]);

    $result = app(MarkInstallmentPaid::class)($installment, SettledVia::Transfer, '2026-09-01', 'RE-1', null, 40000);

    expect($result->status)->toBe(PaymentInstallmentStatus::Partial)
        ->and($result->paid_amount)->toBe(40000)
        ->and($result->remaining())->toBe(60000);

    // The deposit that books a project is the deposit, not part of it.
    Event::assertNotDispatched(InstallmentPaid::class);
});

it('adds a second booking up and completes the installment', function () {
    Event::fake([InstallmentPaid::class]);

    $installment = PaymentInstallment::factory()->create(['amount' => 100000]);

    $action = app(MarkInstallmentPaid::class);
    $action($installment, SettledVia::Transfer, '2026-09-01', 'RE-1', null, 40000);
    $result = $action($installment->fresh(), SettledVia::Transfer, '2026-09-08', 'RE-2', null, 60000);

    expect($result->status)->toBe(PaymentInstallmentStatus::Paid)
        ->and($result->paid_amount)->toBe(100000)
        ->and($result->remaining())->toBe(0)
        ->and($result->payment_reference)->toBe('RE-2');

    Event::assertDispatchedTimes(InstallmentPaid::class, 1);
});

it('defaults the booked amount to what is still owed', function () {
    $installment = PaymentInstallment::factory()->create(['amount' => 100000, 'paid_amount' => 25000]);

    $result = app(MarkInstallmentPaid::class)($installment, SettledVia::Transfer, '2026-09-01', null);

    expect($result->status)->toBe(PaymentInstallmentStatus::Paid)
        ->and($result->paid_amount)->toBe(100000);
});

it('records the booked amount and what is left in the audit context', function () {
    $actor = staffMember(StaffRole::Admin);
    $installment = PaymentInstallment::factory()->create(['amount' => 100000]);

    app(MarkInstallmentPaid::class)($installment, SettledVia::Transfer, '2026-09-01', null, $actor, 40000);

    $row = AuditLog::where('event', 'payment_installment.paid')->where('subject', $installment->public_id)->sole();
    expect($row->context['amount'])->toBe(40000)
        ->and($row->context['remaining'])->toBe(60000);
});

it('books a partial payment through the admin screen', function () {
    $plan = t07PaidPlan();
    $installment = $plan->installments->sole();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->post("/raten/{$installment->public_id}/bezahlt", [
            'paid_on' => '2026-09-01',
            'method' => 'transfer',
            'amount' => 30000,
        ])
        ->assertRedirect();

    expect($installment->fresh()->status)->toBe(PaymentInstallmentStatus::Partial)
        ->and($installment->fresh()->paid_amount)->toBe(30000);
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
    $user = staffMember(StaffRole::Admin);

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
    $user = staffMember(StaffRole::Admin);

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
    [StaffRole::Admin, 302],
    [StaffRole::Manager, 302],
    [StaffRole::Contributor, 403],
    [StaffRole::Reader, 403],
]);

it('rejects an unknown settlement method', function () {
    $plan = t07PaidPlan();
    $installment = $plan->installments->sole();
    $user = staffMember(StaffRole::Admin);

    $this->actingAs($user, 'staff')
        ->from('/auftraege/xyz')
        ->post("/raten/{$installment->public_id}/bezahlt", [
            'paid_on' => '2026-09-01',
            'method' => 'bitcoin',
        ])
        ->assertSessionHasErrors('method');
});
