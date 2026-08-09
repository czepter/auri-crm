<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Media\Models\MultipartUpload;
use Database\Factories\Gallery\GalleryFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * A delivery gallery's draft state, plus the family every GalleryVersion
 * belongs to. shoot_id is nullable because a gallery frequently spans the
 * whole project rather than one shoot (G-05).
 *
 * title and assets here are the working copy — freely editable, never what a
 * guest is served. publish() is the only way their contents reach a
 * GalleryVersion, and once written that row never changes again; see
 * publish() for why, and G-08 (version-scoped caching) for the story that
 * relies on it.
 */
class Gallery extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'project_id', 'shoot_id', 'title', 'assets',
        'requires_settled_balance', 'balance_settled',
    ];

    protected $attributes = [
        'assets' => '[]',
        'requires_settled_balance' => false,
        'balance_settled' => false,
    ];

    protected function casts(): array
    {
        return [
            'assets' => 'array',
            'requires_settled_balance' => 'boolean',
            'balance_settled' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function shoot(): BelongsTo
    {
        return $this->belongsTo(Shoot::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(GalleryVersion::class);
    }

    /** The most recently published version, or null before this gallery's first publish() call. */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(GalleryVersion::class)->latestOfMany('version');
    }

    /** Every multipart upload session (G-02) ever started into this gallery's quarantine, whatever its state. */
    public function multipartUploads(): HasMany
    {
        return $this->hasMany(MultipartUpload::class);
    }

    /**
     * Snapshots title/assets into a brand new GalleryVersion and leaves every
     * earlier one untouched — no update, no delete. The transaction only
     * wraps the read-then-insert (the same shape ContractTemplate::
     * newVersion() uses for its own family), so two concurrent publishes
     * either serialize on it or one loses the unique (tenant_id, gallery_id,
     * version) index, never both landing "version 2".
     */
    public function publish(): GalleryVersion
    {
        return DB::transaction(function () {
            $next = 1 + (int) $this->versions()->max('version');

            return $this->versions()->create([
                'version' => $next,
                'title' => $this->title,
                'assets' => $this->assets,
                'published_at' => now(),
            ]);
        });
    }

    protected static function newFactory(): Factory
    {
        return GalleryFactory::new();
    }
}
