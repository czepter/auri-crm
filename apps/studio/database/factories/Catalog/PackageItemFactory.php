<?php

namespace Database\Factories\Catalog;

use App\Catalog\Models\PackageItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageItem>
 */
class PackageItemFactory extends Factory
{
    protected $model = PackageItem::class;

    public function definition(): array
    {
        return [
            'title' => 'Fotograf ganztägig',
            'description' => null,
            'quantity' => 1,
            'unit_price' => 129900,
        ];
    }

    /** Enthaltene Leistung: part of the package, priced on nothing of its own. */
    public function included(): static
    {
        return $this->state(fn () => ['unit_price' => null]);
    }
}
