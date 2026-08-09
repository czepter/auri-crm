<?php

namespace Database\Factories\Gallery;

use App\Gallery\Models\AssetComment;
use App\Gallery\Models\GalleryVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetComment>
 */
class AssetCommentFactory extends Factory
{
    protected $model = AssetComment::class;

    public function definition(): array
    {
        return [
            'gallery_version_id' => GalleryVersion::factory(),
            'asset_key' => 'galleries/g1/'.fake()->uuid().'.jpg',
            'guest_identifier' => (string) fake()->uuid(),
            'body' => fake()->sentence(),
        ];
    }
}
