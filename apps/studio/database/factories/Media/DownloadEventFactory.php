<?php

namespace Database\Factories\Media;

use App\Gallery\Models\GalleryVersion;
use App\Media\Models\DownloadEvent;
use App\Media\Models\ZipBuild;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DownloadEvent>
 */
class DownloadEventFactory extends Factory
{
    protected $model = DownloadEvent::class;

    public function definition(): array
    {
        return [
            'gallery_version_id' => GalleryVersion::factory(),
            'zip_build_id' => ZipBuild::factory(),
            'requester_identifier' => fake()->uuid(),
            'ip' => fake()->ipv4(),
            'request_date' => now()->toDateString(),
        ];
    }
}
