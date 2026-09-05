<?php

namespace Database\Factories\Media;

use App\Gallery\Models\GalleryVersion;
use App\Media\Enums\ZipBuildScope;
use App\Media\Enums\ZipBuildSize;
use App\Media\Enums\ZipBuildStatus;
use App\Media\Models\ZipBuild;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ZipBuild>
 */
class ZipBuildFactory extends Factory
{
    protected $model = ZipBuild::class;

    public function definition(): array
    {
        return [
            'gallery_version_id' => GalleryVersion::factory(),
            'scope' => ZipBuildScope::All,
            'size' => ZipBuildSize::Original,
            'status' => ZipBuildStatus::Building,
        ];
    }
}
