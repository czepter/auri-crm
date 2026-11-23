<?php

namespace Database\Factories\Finance;

use App\Finance\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalYear>
 */
class FiscalYearFactory extends Factory
{
    protected $model = FiscalYear::class;

    public function definition(): array
    {
        return ['year' => now()->year - 1, 'vat_mode' => 'standard'];
    }

    public function locked(): static
    {
        return $this->state(fn () => ['locked_at' => now(), 'snapshot' => []]);
    }
}
