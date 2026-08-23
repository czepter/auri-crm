<?php

namespace App\Media\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Gallery\Models\Gallery;
use App\Gallery\Models\GallerySet;
use App\Media\Enums\MultipartUploadStatus;
use Database\Factories\Media\MultipartUploadFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One resumable S3 multipart upload session, quarantined under
 * ReapIncompleteMultipartUploads::QUARANTINE_PREFIX until it either
 * completes or is aborted. See App\Media\Actions\StartOrResumeMultipartUpload
 * for how a returning browser tab finds this same row again by fingerprint
 * (filename, file_size, file_modified_at) rather than by any id it was never
 * given.
 *
 * Deliberately holds no record of which parts have landed — the bucket is
 * the source of truth for that (App\Media\Services\MultipartUploadGateway::
 * listParts), per #51's own "on resume, the bucket is the source of truth
 * for what actually landed" ruling. This row exists to be found again, not
 * to duplicate what ListParts already answers.
 */
class MultipartUpload extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'public_id', 'gallery_id', 'gallery_set_id', 'replaces_asset_id', 'key', 'upload_id', 'filename', 'file_size',
        'file_modified_at', 'part_size', 'status', 'completed_at', 'aborted_at',
        'processed_at', 'asset_id',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'file_modified_at' => 'datetime',
            'part_size' => 'integer',
            'status' => MultipartUploadStatus::class,
            'completed_at' => 'datetime',
            'aborted_at' => 'datetime',
            'processed_at' => 'datetime',
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

    /**
     * The asset G-03's App\Media\Actions\IngestCompletedUpload concluded
     * this upload as — set on every conclusion, including a duplicate
     * (checksum already known) and a Failed one, never only on success.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    protected static function newFactory(): Factory
    {
        return MultipartUploadFactory::new();
    }
}
