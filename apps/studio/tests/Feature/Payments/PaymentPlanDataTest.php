<?php

use App\Payments\Data\PaymentPlanData;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('marks an unpaid installment past its due date as late', function () {
    $installment = PaymentInstallment::factory()->create([
        'due_on' => now()->subDay()->toDateString(),
        'status' => PaymentInstallmentStatus::Scheduled,
    ]);

    expect(PaymentPlanData::fromModel($installment->plan->load('installments'))
        ->installments[0]->isLate)->toBeTrue();
});

it('does not mark a paid installment late, however old', function () {
    $installment = PaymentInstallment::factory()->create([
        'due_on' => now()->subYear()->toDateString(),
        'status' => PaymentInstallmentStatus::Paid,
    ]);

    expect(PaymentPlanData::fromModel($installment->plan->load('installments'))
        ->installments[0]->isLate)->toBeFalse();
});

it('marks an issued invoice past its due date as late', function () {
    $installment = PaymentInstallment::factory()->create([
        'due_on' => now()->subDay()->toDateString(),
        'status' => PaymentInstallmentStatus::Invoiced,
    ]);

    expect(PaymentPlanData::fromModel($installment->plan->load('installments'))
        ->installments[0]->isLate)->toBeTrue();
});
