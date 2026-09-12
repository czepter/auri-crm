<?php

namespace Database\Factories\Quotes;

use App\Crm\Models\Project;
use App\Quotes\Enums\PricingMode;
use App\Quotes\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
        ];
    }

    /** Q-08: a quote the client configures rather than one it only accepts. */
    public function slider(int $rate = 25_000, int $min = 4, int $max = 10): static
    {
        return $this->state(fn () => [
            'pricing_mode' => PricingMode::Choice,
            'hourly_rate' => $rate,
            'min_hours' => $min,
            'max_hours' => $max,
            'images_per_hour' => 40,
            'hours' => $min,
        ]);
    }
}
