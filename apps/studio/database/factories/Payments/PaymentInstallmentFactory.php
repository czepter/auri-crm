<?php

namespace Database\Factories\Payments;

use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Models\PaymentPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentInstallment>
 */
class PaymentInstallmentFactory extends Factory
{
    protected $model = PaymentInstallment::class;

    public function definition(): array
    {
        return [
            'payment_plan_id' => PaymentPlan::factory(),
            'amount' => 100000,
            'due_on' => now()->addMonth()->toDateString(),
            'status' => PaymentInstallmentStatus::Scheduled,
            'sort_order' => 0,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => PaymentInstallmentStatus::Paid]);
    }
}
