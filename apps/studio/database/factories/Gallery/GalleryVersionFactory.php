<?php

namespace Database\Factories\Gallery;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GalleryVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryVersion>
 */
class GalleryVersionFactory extends Factory
{
    protected $model = GalleryVersion::class;

    public function definition(): array
    {
        return [
            'gallery_id' => Gallery::factory(),
            'version' => 1,
            'title' => fake()->words(2, true),
            'assets' => [],
            'published_at' => now(),
        ];
    }
}
