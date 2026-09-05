<?php

namespace Database\Factories\Gallery;

use App\Crm\Models\Project;
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
            'project_id' => Project::factory(),
            'title' => fake()->words(2, true),
        ];
    }
}
