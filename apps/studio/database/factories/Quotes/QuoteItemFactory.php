<?php

namespace Database\Factories\Quotes;

use App\Quotes\Models\QuoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteItem>
 */
class QuoteItemFactory extends Factory
{
    protected $model = QuoteItem::class;

    public function definition(): array
    {
        return [
            'title' => 'Hochzeitsreportage Ganztag',
            'description' => 'bis 14 Std. am Hochzeitstag, Bearbeitung, Online-Galerie',
            'quantity' => 1,
            'unit_price' => 440000,
        ];
    }

    /** Enthaltene Leistung: part of the quote, priced on nothing of its own. */
    public function included(): static
    {
        return $this->state(fn () => ['unit_price' => null]);
    }
}
