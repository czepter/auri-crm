<?php

namespace App\Media\Models;

use App\Gallery\Models\GalleryList;
use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Gallery\Models\GalleryVersion;
use App\Media\Enums\ZipBuildScope;
use App\Media\Enums\ZipBuildSize;
use App\Media\Enums\ZipBuildStatus;
use Database\Factories\Media\ZipBuildFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One deduped (gallery_version_id, scope, size) archive build (G-10, #59) —
 * see the migration's unique index, which is the actual dedupe mechanism;
 * App\Media\Actions\RequestZipBuild is just what finds-or-creates against it.
 * One or more ZipBuildPart rows carry the object key(s) split produced it,
 * per the 3.9GiB volume boundary — the same one-to-many precedent
 * Asset/AssetDerivative (G-03) already set.
 */
class ZipBuild extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['gallery_version_id', 'scope', 'size', 'status', 'error', 'gallery_list_id'];

    protected function casts(): array
    {
        return [
            'scope' => ZipBuildScope::class,
            'size' => ZipBuildSize::class,
            'status' => ZipBuildStatus::class,
        ];
    }

    public function galleryVersion(): BelongsTo
    {
        return $this->belongsTo(GalleryVersion::class);
    }

    /** In split order — see ZipBuildPart::part_number. */
    /** G-03 (#231): the selection this archive covers, or null for the whole version. */
    public function galleryList(): BelongsTo
    {
        return $this->belongsTo(GalleryList::class, 'gallery_list_id');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(ZipBuildPart::class)->orderBy('part_number');
    }

    protected static function newFactory(): Factory
    {
        return ZipBuildFactory::new();
    }
}
