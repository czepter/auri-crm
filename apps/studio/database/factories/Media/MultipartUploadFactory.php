<?php

namespace Database\Factories\Media;

use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Enums\MultipartUploadStatus;
use App\Media\Models\MultipartUpload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MultipartUpload>
 */
class MultipartUploadFactory extends Factory
{
    protected $model = MultipartUpload::class;

    public function definition(): array
    {
        $publicId = (string) fake()->uuid();
        $filename = fake()->lexify('IMG_????.jpg');

        return [
            'gallery_id' => Gallery::factory(),
            'gallery_set_id' => fn (array $attributes) => Gallery::find($attributes['gallery_id'])?->sets()->first()?->id
                ?? GallerySet::factory()->create(['gallery_id' => $attributes['gallery_id']])->id,
            'key' => MultipartUpload::QUARANTINE_PREFIX.$publicId.'/'.$filename,
            'upload_id' => fake()->uuid(),
            'filename' => $filename,
            'file_size' => fake()->numberBetween(1_000_000, 50_000_000),
            'file_modified_at' => now(),
            'part_size' => 8 * 1024 * 1024,
            'status' => MultipartUploadStatus::InProgress,
        ];
    }
}
