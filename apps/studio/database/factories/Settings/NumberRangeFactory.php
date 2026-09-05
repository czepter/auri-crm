<?php

namespace Database\Factories\Settings;

use App\Numbering\Enums\DocumentType;
use App\Numbering\Enums\NumberRangeResetRule;
use App\Settings\Models\NumberRange;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NumberRange>
 */
class NumberRangeFactory extends Factory
{
    protected $model = NumberRange::class;

    public function definition(): array
    {
        return [
            // A fresh tenant per row by default, the same isolation
            // AllocateQuoteNumberTest (Q-05) used to run its scenarios and
            // its concurrency proof without colliding with each other or
            // with the one seeded tenant's own ranges.
            'tenant_id' => (string) Str::uuid(),
            'document_type' => DocumentType::Quote,
            'prefix' => 'AN',
            'reset_rule' => NumberRangeResetRule::Yearly,
            'starting_counter' => 1,
        ];
    }
}
