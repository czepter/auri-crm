<?php

namespace Database\Factories\Quotes;

use App\Crm\Models\Project;
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
}
