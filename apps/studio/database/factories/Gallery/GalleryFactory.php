<?php

namespace Database\Factories\Gallery;

use App\Crm\Models\ClientCase;
use App\Gallery\Models\Gallery;
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
}
