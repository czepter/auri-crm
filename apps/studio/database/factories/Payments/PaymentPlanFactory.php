<?php

namespace Database\Factories\Payments;

use App\Crm\Models\Job;
use App\Payments\Models\PaymentPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentPlan>
 */
class PaymentPlanFactory extends Factory
{
    protected $model = PaymentPlan::class;

    public function definition(): array
    {
        // Zero, not a fixture-shaped number: zero installments legitimately
        // sum to zero, so a bare PaymentPlan::factory()->create() satisfies
        // the sum-matches-total trigger on its own. A test that needs
        // installments sets total to match what it creates.
        return [
            'job_id' => Job::factory(),
            'total' => 0,
        ];
    }
}
