<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\StudioSettings;
use App\Payments\Actions\DetectOverdueInstallments;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Actions\MarkInstallmentPaid;
use App\Payments\Actions\SettlePaymentPlan;
use App\Payments\Enums\CancellationKind;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Enums\SettledVia;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Settings\Actions\UpdateStudioSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Bus::fake();
    app(UpdateStudioSetting::class)('tax_number', '22/123/45678', null);
});

/**
 * One plan with the three states the dialog decides over: a paid Anzahlung,
 * an issued and unpaid rate, and one nobody has issued.
 *
 * @return array{0: PaymentPlan, 1: PaymentInstallment, 2: PaymentInstallment, 3: PaymentInstallment}
 */
function threeStatePlan(): array
{
    $installment = invoiceablePlan();
    $plan = $installment->plan;

    $plan->installments()->whereKeyNot($installment->id)->delete();
    $plan->forceFill(['total' => 150_000])->save();

    $paid = $installment;
    $paid->forceFill(['amount' => 50_000, 'sort_order' => 0])->save();

    $issued = $plan->installments()->create([
        'amount' => 60_000,
        'due_on' => '2026-10-01',
        'sort_order' => 1,
    ]);

    $open = $plan->installments()->create([
        'amount' => 40_000,
        'due_on' => '2026-11-01',
        'sort_order' => 2,
    ]);

    app(IssueInvoice::class)($paid);
    app(MarkInstallmentPaid::class)($paid->fresh(), SettledVia::Transfer, '2026-09-02', 'ref');
    app(IssueInvoice::class)($issued);

    return [$plan->fresh(), $paid->fresh(), $issued->fresh(), $open->fresh()];
}

it('deletes the unissued rate, voids the issued one and keeps the paid one', function () {
    [$plan, $paid, $issued, $open] = threeStatePlan();

    app(SettlePaymentPlan::class)($plan, [], staffMember(StaffRole::Admin));

    expect(PaymentInstallment::query()->whereKey($open->id)->exists())->toBeFalse()
        ->and($issued->fresh()->status)->toBe(PaymentInstallmentStatus::Cancelled)
        ->and($issued->fresh()->invoice->cancellation_kind)->toBe(CancellationKind::Void)
        ->and($paid->fresh()->status)->toBe(PaymentInstallmentStatus::Paid)
        // Was noch lebt, ist die behaltene Rate — und genau das ist die
        // Plansumme, weil der Summen-Trigger beides zusammen prüft.
        ->and($plan->fresh()->total)->toBe(50_000)
        ->and($plan->fresh()->settled_at)->not->toBeNull();
});

it('writes the decisions into the audit log', function () {
    [$plan, $paid] = threeStatePlan();

    app(SettlePaymentPlan::class)($plan, [], staffMember(StaffRole::Admin));

    $entry = AuditLog::query()->where('event', 'payment_plan.settled')->sole();

    expect($entry->context['decisions'][$paid->public_id]['action'])->toBe('keep')
        ->and($entry->context['total'])->toBe(50_000);
});

it('turns a credit decision into a Gutschrift', function () {
    [$plan, $paid] = threeStatePlan();

    app(SettlePaymentPlan::class)($plan, [
        $paid->public_id => ['action' => 'credit', 'amount' => 20_000],
    ], staffMember(StaffRole::Admin));

    $note = $paid->fresh()->invoice->creditNotes->sole();

    expect($note->amount)->toBe(20_000)
        ->and($note->number)->toStartWith('GS-')
        ->and($plan->fresh()->total)->toBe(50_000);
});

it('sends no reminder once the plan is settled', function () {
    Notification::fake();
    StudioSettings::query()->update(['payments_reminders_enabled' => true]);
    Cache::store('redis')->flush();

    [$plan, , $issued] = threeStatePlan();

    // Die Rate ist überfällig und in der Leiter, bevor abgewickelt wird.
    $issued->forceFill(['due_on' => now()->subDays(4)->toDateString()])->save();
    app(DetectOverdueInstallments::class)();

    app(SettlePaymentPlan::class)($plan, []);

    Notification::fake();
    $this->travel(6)->days();
    app(DetectOverdueInstallments::class)();

    Notification::assertNothingSent();
});

it('refuses a second settlement', function () {
    [$plan] = threeStatePlan();

    app(SettlePaymentPlan::class)($plan, []);

    expect(fn () => app(SettlePaymentPlan::class)($plan->fresh(), []))
        ->toThrow(ValidationException::class);
});
