<?php

namespace Database\Factories\Quotes;

use App\Quotes\Models\Quote;
use App\Quotes\Models\QuotePaymentStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotePaymentStage>
 */
class QuotePaymentStageFactory extends Factory
{
    protected $model = QuotePaymentStage::class;

    public function definition(): array
    {
        return [
            'quote_id' => Quote::factory(),
            'percent' => 50,
            'label' => 'Bei der Zusage',
            'minimum' => 0,
            'sort_order' => 0,
        ];
    }
}
