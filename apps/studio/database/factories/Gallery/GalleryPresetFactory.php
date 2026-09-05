<?php

namespace Database\Factories\Gallery;

use App\Gallery\Models\GalleryPreset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryPreset>
 */
class GalleryPresetFactory extends Factory
{
    protected $model = GalleryPreset::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'expiry_mode' => 'inherit',
        ];
    }
}
