<?php

namespace Database\Factories\Media;

use App\Media\Models\ZipBuild;
use App\Media\Models\ZipBuildPart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ZipBuildPart>
 */
class ZipBuildPartFactory extends Factory
{
    protected $model = ZipBuildPart::class;

    public function definition(): array
    {
        return [
            'zip_build_id' => ZipBuild::factory(),
            'part_number' => 1,
            'key' => 'galleries/test/zips/'.fake()->uuid().'/part-1.zip',
            'byte_size' => fake()->numberBetween(100_000, 20_000_000),
        ];
    }
}
