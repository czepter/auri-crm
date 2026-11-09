<?php

namespace App\Media\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Enums\AssetStatus;
use Database\Factories\Media\AssetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One SHA-256-deduplicated original ingested into a gallery (G-03), plus
 * everything App\Gallery\Actions\IngestCompletedUpload derived from it. Written
 * exactly once per (tenant, gallery, checksum) — see the migration's partial
 * unique index — so a retry, a redelivered queue message, or the same file
 * uploaded twice through the browser all converge on this same row rather
 * than a duplicate.
 *
 * A Failed row (AssetStatus::Failed) carries whatever the pipeline reached
 * before it gave up — often just checksum and filename — plus `error`. It is
 * never retried automatically; G-04's "needs attention" tray is what an
 * operator uses to act on it.
 */
class Asset extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'public_id', 'gallery_id', 'gallery_set_id', 'checksum', 'status', 'error',
        'removed_from_draft_at', 'key', 'filename', 'byte_size', 'width', 'height',
        'taken_at', 'camera_make', 'camera_model', 'phash', 'blurhash', 'copied_from_asset_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'byte_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'taken_at' => 'datetime',
            'removed_from_draft_at' => 'datetime',
        ];
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(GallerySet::class, 'gallery_set_id');
    }

    public function derivatives(): HasMany
    {
        return $this->hasMany(AssetDerivative::class);
    }

    /**
     * Was der Entwurf zeigt. Versionsgebundene Leser (ZIP, Bildauslieferung)
     * fragen das nie.
     *
     * status=Ready gehoert hierher, nicht in jeden einzelnen Aufrufer: ein
     * Failed-Datensatz hat keinen key, keine Masse, nichts, das ein Raster
     * oder ein Snapshot zeigen koennte. G-04's Tray fragt selbst nach
     * status, nicht nach diesem Scope.
     */
    public function scopeInDraft(Builder $query): void
    {
        $query->whereNull('removed_from_draft_at')->where('status', AssetStatus::Ready);
    }

    protected static function newFactory(): Factory
    {
        return AssetFactory::new();
    }
}
