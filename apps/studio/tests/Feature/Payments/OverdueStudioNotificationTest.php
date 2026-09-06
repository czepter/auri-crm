<?php

use App\Models\User;
use App\Notifications\Studio\InstallmentOverdue;
use App\Payments\Actions\DetectOverdueInstallments;
use App\Payments\Enums\OverdueReason;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function lateInstallment(int $daysLate = 1): PaymentInstallment
{
    return PaymentInstallment::factory()->create([
        'payment_plan_id' => PaymentPlan::factory(),
        'due_on' => now()->subDays($daysLate)->toDateString(),
    ]);
}

// Die Asymmetrie, die T-22 schliesst: InstallmentPaid hing an beiden
// Listenern, InstallmentOverdue nur am Webhook.
it('tells the studio when the scan finds an overdue installment', function () {
    Notification::fake();
    staffMember();

    lateInstallment();

    app(DetectOverdueInstallments::class)();

    Notification::assertSentTo(User::query()->get(), InstallmentOverdue::class);
});

it('repeats on the next rung and not before', function () {
    Notification::fake();
    $staff = staffMember();

    $installment = lateInstallment(1);

    app(DetectOverdueInstallments::class)();
    expect(app(DetectOverdueInstallments::class)())->toBe(0);

    Notification::assertSentToTimes($staff, InstallmentOverdue::class, 1);

    $this->travel(3)->days();
    app(DetectOverdueInstallments::class)();

    Notification::assertSentToTimes($staff, InstallmentOverdue::class, 2);
    expect($installment->fresh()->overdue_reminders_sent)->toBe(1);
});

it('names the reason a failed payment gave', function () {
    $installment = lateInstallment();

    app(DetectOverdueInstallments::class)->markOverdue($installment, OverdueReason::PaymentFailed);

    expect($installment->fresh()->overdue_reason)->toBe(OverdueReason::PaymentFailed);
});
