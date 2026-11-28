<?php

use App\Payments\Actions\DetectOverdueInstallments;
use App\Payments\Actions\IssueInvoice;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Payments\Notifications\PaymentReminder;
use App\Payments\Notifications\Studio\InstallmentDueSoon;
use App\Settings\Actions\UpdateStudioSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function dueIn(int $days, PaymentInstallmentStatus $status = PaymentInstallmentStatus::Invoiced): PaymentInstallment
{
    return PaymentInstallment::factory()->create([
        'payment_plan_id' => PaymentPlan::factory(),
        'due_on' => now()->addDays($days)->toDateString(),
        'status' => $status,
    ]);
}

it('tells the studio three days before an issued rate falls due', function () {
    Notification::fake();
    $staff = staffMember();

    $installment = dueIn(3);

    app(DetectOverdueInstallments::class)();

    Notification::assertSentTo($staff, InstallmentDueSoon::class);
    expect($installment->fresh()->before_due_reminder_sent_at)->not->toBeNull();
});

it('says it once', function () {
    Notification::fake();
    $staff = staffMember();

    dueIn(3);

    app(DetectOverdueInstallments::class)();
    app(DetectOverdueInstallments::class)();

    Notification::assertSentToTimes($staff, InstallmentDueSoon::class, 1);
});

it('stays quiet four days out', function () {
    Notification::fake();
    $staff = staffMember();

    dueIn(4);

    app(DetectOverdueInstallments::class)();

    Notification::assertNotSentTo($staff, InstallmentDueSoon::class);
});

// Ein geplanter Termin im Plan ist keine Forderung: der Kunde hat nichts in
// der Hand, das er bezahlen könnte.
it('stays quiet for a rate nobody has issued', function () {
    Notification::fake();
    $staff = staffMember();

    dueIn(3, PaymentInstallmentStatus::Scheduled);

    app(DetectOverdueInstallments::class)();

    Notification::assertNotSentTo($staff, InstallmentDueSoon::class);
});

it('writes the reminder in the studio’s own wording, merge fields resolved', function () {
    app(UpdateStudioSetting::class)('tax_number', '22/123/45678', null);
    mailDefault('payment_reminder', 'Erinnerung {{ reminder.invoiceNumber }}', 'Hallo {{ contact.name }}, {{ reminder.amount }} sind seit {{ reminder.dueOn }} offen.');

    $installment = invoiceablePlan();
    $invoice = app(IssueInvoice::class)($installment);
    $installment->refresh();

    $draft = (new PaymentReminder($installment))->draft($installment->plan->case->primaryContact());

    expect($draft['subject'])->toBe("Erinnerung {$invoice->number}")
        ->and($draft['body'])->toContain('sind seit '.$installment->due_on->format('d.m.Y').' offen');
});
