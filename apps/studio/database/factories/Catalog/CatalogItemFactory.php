<?php

namespace Database\Factories\Catalog;

use App\Catalog\Models\CatalogItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogItem>
 */
class CatalogItemFactory extends Factory
{
    protected $model = CatalogItem::class;

    public function definition(): array
    {
        return [
            // Unique: catalog_items carries a unique index on (tenant_id, title).
            'title' => fake()->unique()->words(2, true),
            'description' => null,
            'unit_price' => 49000,
        ];
    }

    public function included(): static
    {
        return $this->state(fn () => ['unit_price' => null]);
    }
}
