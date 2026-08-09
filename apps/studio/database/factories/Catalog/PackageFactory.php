<?php

namespace Database\Factories\Catalog;

use App\Catalog\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        return [
            'name' => 'Hochzeit Basis',
            'description' => 'Ganztägige Begleitung mit einem Fotografen.',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
