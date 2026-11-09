<?php

namespace Database\Factories\Gallery;

use App\Crm\Models\ClientCase;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gallery>
 */
class GalleryFactory extends Factory
{
    protected $model = Gallery::class;

    public function definition(): array
    {
        return [
            'case_id' => ClientCase::factory(),
            'title' => fake()->words(2, true),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Gallery $gallery) => $gallery->sets()->update(['access' => GallerySet::GUESTS]));
    }
}
