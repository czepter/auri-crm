<?php

use App\Payments\Actions\DetectOverdueInstallments;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use App\Payments\Notifications\Studio\InstallmentDueSoon;
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
