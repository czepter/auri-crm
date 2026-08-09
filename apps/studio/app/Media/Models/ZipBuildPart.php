<?php

namespace App\Media\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Media\ZipBuildPartFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One archive volume of a ZipBuild — one S3 object, written by
 * App\Media\Services\MultipartZipUploader and closed once the in-progress
 * archive would exceed the 3.9GiB split boundary (#59).
 */
class ZipBuildPart extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['zip_build_id', 'part_number', 'key', 'byte_size'];

    protected function casts(): array
    {
        return [
            'part_number' => 'integer',
            'byte_size' => 'integer',
        ];
    }

    public function zipBuild(): BelongsTo
    {
        return $this->belongsTo(ZipBuild::class);
    }

    protected static function newFactory(): Factory
    {
        return ZipBuildPartFactory::new();
    }
}
