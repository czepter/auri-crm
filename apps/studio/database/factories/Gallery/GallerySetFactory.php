<?php

namespace Database\Factories\Gallery;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GallerySet>
 */
class GallerySetFactory extends Factory
{
    protected $model = GallerySet::class;

    public function definition(): array
    {
        return [
            'gallery_id' => Gallery::factory(),
            'name' => fake()->unique()->word(),
            'position' => 0,
        ];
    }
}
