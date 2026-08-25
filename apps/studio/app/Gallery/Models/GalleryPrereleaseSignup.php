<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * R42: one row per address waiting to hear that a gallery went live. Sent
 * exactly once — notified_at marks that — and never again, so a second
 * publish() call finds nothing left to send.
 */
class GalleryPrereleaseSignup extends Model
{
    use BelongsToTenant;

    protected $fillable = ['gallery_id', 'email'];

    protected function casts(): array
    {
        return [
            'notified_at' => 'immutable_datetime',
        ];
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function scopeNotNotified(Builder $query): void
    {
        $query->whereNull('notified_at');
    }

    /**
     * mb_strtolower, the same normalisation RequestClientAccess's email
     * flows already use — capitals are the one way two people typing the
     * same address disagree, and the unique index below is only as good as
     * what gets written into it.
     */
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
