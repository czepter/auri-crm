<?php

namespace Database\Factories\Media;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Enums\AssetStatus;
use App\Media\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'gallery_id' => Gallery::factory(),
            'gallery_set_id' => fn (array $attributes) => Gallery::find($attributes['gallery_id'])?->sets()->first()?->id
                ?? GallerySet::factory()->create(['gallery_id' => $attributes['gallery_id']])->id,
            'checksum' => hash('sha256', fake()->unique()->uuid()),
            'status' => AssetStatus::Ready,
            'key' => 'galleries/test/'.fake()->uuid().'/original.jpg',
            'filename' => fake()->lexify('IMG_????.jpg'),
            'byte_size' => fake()->numberBetween(100_000, 20_000_000),
            'width' => 4000,
            'height' => 3000,
        ];
    }
}
