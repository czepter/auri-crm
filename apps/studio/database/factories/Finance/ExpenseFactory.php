<?php

namespace Database\Factories\Finance;

use App\Finance\Enums\ExpenseStatus;
use App\Finance\Models\Expense;
use App\Finance\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'vendor' => 'Nordhost Hosting GmbH',
            'invoice_date' => now()->subMonth()->toDateString(),
            'paid_at' => now()->subMonth()->toDateString(),
            'net' => 10000,
            'vat_rate' => 1900,
            'vat' => 1900,
            'gross' => 11900,
            'currency' => 'EUR',
            'gross_eur' => 11900,
            'category_id' => fn () => ExpenseCategory::query()->where('key', 'sonstiges')->value('id'),
            'business_share' => 100,
            'status' => ExpenseStatus::Booked,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => ExpenseStatus::Draft]);
    }
}
