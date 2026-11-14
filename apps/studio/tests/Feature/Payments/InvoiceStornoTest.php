<?php

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Payments\Actions\CancelInvoice;
use App\Payments\Actions\DetectOverdueInstallments;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Data\PaymentPlanData;
use App\Payments\Enums\CancellationKind;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Settings\Actions\UpdateStudioSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(UpdateStudioSetting::class)('tax_number', '22/123/45678', null);
});

function stornoableInstallment(): PaymentInstallment
{
    Storage::fake('local');
    Bus::fake();

    $installment = invoiceablePlan();
    app(IssueInvoice::class)($installment);

    return $installment->fresh();
}

it('hands a corrected amount back to the plan as a fresh scheduled rate, total untouched', function () {
    $installment = stornoableInstallment();
    $plan = $installment->plan;
    $amount = $installment->amount;

    app(CancelInvoice::class)($installment->invoice, CancellationKind::Correction, 'Falscher Empfänger');

    $plan->refresh()->load('installments');

    expect($plan->total)->toBe(150_000)
        ->and($plan->installments)->toHaveCount(3);

    $replacement = $plan->installments->last();

    expect($replacement->amount)->toBe($amount)
        ->and($replacement->status)->toBe(PaymentInstallmentStatus::Scheduled)
        ->and($replacement->invoice_id)->toBeNull()
        ->and($replacement->due_on->format('Y-m-d'))->toBe('2026-09-01');
});

it('lowers the plan total when the work itself falls away', function () {
    $installment = stornoableInstallment();
    $plan = $installment->plan;

    app(CancelInvoice::class)($installment->invoice, CancellationKind::Void, 'Termin abgesagt');

    $plan->refresh()->load('installments');

    expect($plan->total)->toBe(150_000 - $installment->amount)
        ->and($plan->installments)->toHaveCount(2)
        ->and($plan->installments->reject->isCancelled())->toHaveCount(1);
});

it('keeps the number, the date and the document on the cancelled invoice', function () {
    $installment = stornoableInstallment();
    $invoice = $installment->invoice;

    app(CancelInvoice::class)($invoice, CancellationKind::Void, 'Termin abgesagt');

    $invoice->refresh();

    expect($invoice->number)->toStartWith('RE-')
        ->and($invoice->issued_at)->not->toBeNull()
        ->and($invoice->status())->toBe('cancelled')
        ->and($invoice->cancellation_kind)->toBe(CancellationKind::Void)
        ->and($invoice->cancellation_reason)->toBe('Termin abgesagt')
        ->and($installment->fresh()->status)->toBe(PaymentInstallmentStatus::Cancelled)
        ->and($installment->fresh()->invoice_id)->toBe($invoice->id);
});

// #480: the whole document goes, every rate on it.
it('retires every rate an invoice carries', function () {
    Storage::fake('local');
    Bus::fake();

    $rates = invoiceablePlan()->plan->installments()->orderBy('sort_order')->get();
    $invoice = app(IssueInvoice::class)($rates);

    app(CancelInvoice::class)($invoice, CancellationKind::Correction, 'Falscher Empfänger');

    $plan = $rates->first()->plan->fresh()->load('installments');

    expect($rates->map->fresh()->pluck('status')->unique()->all())->toBe([PaymentInstallmentStatus::Cancelled])
        ->and($plan->total)->toBe(150_000)
        ->and($plan->installments->reject->isCancelled())->toHaveCount(2);
});

it('refuses a paid rate — that is a Gutschrift, not a Storno', function () {
    $installment = stornoableInstallment();
    $installment->update(['status' => PaymentInstallmentStatus::Paid, 'paid_amount' => $installment->amount]);

    expect(fn () => app(CancelInvoice::class)($installment->invoice, CancellationKind::Void, 'Zu viel berechnet'))
        ->toThrow(ValidationException::class);

    expect($installment->fresh()->cancelled_at)->toBeNull();
});

it('refuses when a payment lands after the invoice was loaded', function () {
    $installment = stornoableInstallment();
    $invoice = $installment->invoice->load('installments');

    PaymentInstallment::whereKey($installment->id)->update([
        'status' => PaymentInstallmentStatus::Paid,
        'paid_amount' => $installment->amount,
    ]);

    expect(fn () => app(CancelInvoice::class)($invoice, CancellationKind::Void, 'Zu viel berechnet'))
        ->toThrow(ValidationException::class);

    expect($invoice->fresh()->isCancelled())->toBeFalse()
        ->and($installment->fresh()->status)->toBe(PaymentInstallmentStatus::Paid);
});

it('refuses an invoice that is already cancelled', function () {
    $installment = stornoableInstallment();
    app(CancelInvoice::class)($installment->invoice, CancellationKind::Void, 'Vertippt');

    expect(fn () => app(CancelInvoice::class)($installment->invoice->fresh(), CancellationKind::Void, 'Vertippt'))
        ->toThrow(ValidationException::class);
});

it('leaves a cancelled rate out of overdue detection', function () {
    $installment = stornoableInstallment();
    $installment->plan->installments()->where('id', '!=', $installment->id)->update(['due_on' => now()->addMonth()]);
    $installment->update(['due_on' => now()->subMonth()]);

    app(CancelInvoice::class)($installment->invoice, CancellationKind::Void, 'Termin abgesagt');

    expect(app(DetectOverdueInstallments::class)())->toBe(0)
        ->and($installment->fresh()->overdue_notified_at)->toBeNull();
});

it('refuses to issue a second invoice for a cancelled rate', function () {
    $installment = stornoableInstallment();

    app(CancelInvoice::class)($installment->invoice, CancellationKind::Correction, 'Falscher Betrag');

    expect(fn () => app(IssueInvoice::class)($installment->fresh()))
        ->toThrow(ValidationException::class);
});

it('records the kind and the reason', function () {
    $installment = stornoableInstallment();

    app(CancelInvoice::class)($installment->invoice, CancellationKind::Void, 'Termin abgesagt', staffMember(StaffRole::Admin));

    $log = AuditLog::where('event', 'payments.invoice.cancelled')->sole();

    expect($log->context['kind'])->toBe('void')
        ->and($log->context['reason'])->toBe('Termin abgesagt')
        ->and($log->context['number'])->toBe($installment->invoice->number);
});

it('lets staff storno through the route', function () {
    $installment = stornoableInstallment();

    $this->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->post("/rechnungen/{$installment->invoice->public_id}/storno", ['kind' => 'correction', 'reason' => 'Falscher Betrag'])
        ->assertRedirect();

    expect($installment->fresh()->status)->toBe(PaymentInstallmentStatus::Cancelled);
});

it('keeps the surviving rates named as they were before the Storno', function () {
    $installment = stornoableInstallment();
    $labelsBefore = PaymentPlanData::fromModel($installment->plan->load('installments'))
        ->installments;

    app(CancelInvoice::class)($installment->invoice, CancellationKind::Correction, 'Falscher Empfänger');

    $after = PaymentPlanData::fromModel($installment->plan->fresh()->load('installments'))
        ->installments;

    expect($after[1]->label)->toBe($labelsBefore[1]->label)
        ->and($after[2]->label)->toBe($labelsBefore[0]->label);
});

it('still edits a plan that carries a cancelled rate', function () {
    $installment = stornoableInstallment();
    app(CancelInvoice::class)($installment->invoice, CancellationKind::Correction, 'Falscher Empfänger');
    $plan = $installment->plan->fresh();

    test()->actingAs(staffMember(StaffRole::Admin), 'staff')
        ->patch("/zahlungsplaene/{$plan->public_id}", [
            'total' => $plan->total,
            'installments' => $plan->installments->map(fn ($row) => [
                'public_id' => $row->public_id,
                'amount' => $row->amount,
                'due_on' => '2026-12-01',
            ])->all(),
        ])
        ->assertSessionHasNoErrors();
});
