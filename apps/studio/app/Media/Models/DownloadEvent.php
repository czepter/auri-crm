<?php

namespace App\Media\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Gallery\Models\GalleryVersion;
use Database\Factories\Media\DownloadEventFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One request against one ZipBuildPart (G-11, #60) — the full audit trail:
 * who (requester_identifier, ip), what (gallery_version_id, zip_build_id)
 * and when. Written once per HTTP request DownloadZipPartController serves,
 * whether that request is a fresh download or a resumed Range request —
 * the row count is the audit trail, not the bounded quantity.
 *
 * requesterDayCount() is the bounded quantity: distinct
 * (requester_identifier, request_date) pairs. The spike this story fixed
 * counted requests, which charged a resumed transfer for every reconnect;
 * grouping on the calendar day instead makes a reconnect free and only a
 * genuinely new requester on a genuinely new day countable.
 */
class DownloadEvent extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'gallery_version_id', 'zip_build_id', 'requester_identifier', 'ip', 'request_date',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
        ];
    }

    public function galleryVersion(): BelongsTo
    {
        return $this->belongsTo(GalleryVersion::class);
    }

    public function zipBuild(): BelongsTo
    {
        return $this->belongsTo(ZipBuild::class);
    }

    /** Distinct (requester_identifier, request_date) pairs recorded against $zipBuild — the bounded unit, not the row count. */
    public static function requesterDayCount(ZipBuild $zipBuild): int
    {
        return static::query()
            ->where('zip_build_id', $zipBuild->id)
            ->select('requester_identifier', 'request_date')
            ->groupBy('requester_identifier', 'request_date')
            ->get()
            ->count();
    }

    protected static function newFactory(): Factory
    {
        return DownloadEventFactory::new();
    }
}
