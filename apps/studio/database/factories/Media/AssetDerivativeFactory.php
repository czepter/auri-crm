<?php

namespace Database\Factories\Media;

use App\Media\Models\Asset;
use App\Media\Models\AssetDerivative;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetDerivative>
 */
class AssetDerivativeFactory extends Factory
{
    protected $model = AssetDerivative::class;

    public function definition(): array
    {
        $width = fake()->randomElement([400, 800, 1600, 2560]);
        $format = fake()->randomElement(['avif', 'webp']);

        return [
            'asset_id' => Asset::factory(),
            'width' => $width,
            'format' => $format,
            'key' => "galleries/test/derivatives/{$width}.{$format}",
            'bytes' => fake()->numberBetween(5_000, 100_000),
        ];
    }
}
