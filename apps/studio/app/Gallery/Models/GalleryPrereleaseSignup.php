<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * R42: one row per address waiting to hear that a gallery went live.
 *
 * Personal data has no reason to outlive its purpose here, so a row never
 * survives either of this table's two real exits:
 *  - **Notified.** `PublishGallery::notifyPrereleaseSignups()` deletes a
 *    row in the same transaction that dispatches its mail — the row's only
 *    job was holding an address until that moment, and nothing anywhere
 *    reads a notified signup afterwards. `notified_at`/`scopeNotNotified()`
 *    still name the scope correctly (a row that exists is, by
 *    construction, always unnotified); they are not dead code, just never
 *    observed in a non-null state.
 *  - **Pre-release stopped without publishing.**
 *    `GalleryPrereleaseController::destroy()` deletes every pending row for
 *    that gallery — the waiting list belonged to that pre-release window,
 *    not to the gallery for ever, and a studio that resumes pre-release
 *    later asks guests to re-enter their address rather than this table
 *    holding stale ones with no defined end date.
 *
 * Included in a Contact's DSGVO export by address
 * (`ExportContactDsgvoData`) even though nothing here carries a
 * `contact_id` — same reasoning `GalleryEmailVerification` already
 * established: a guest proves nothing but an inbox, so "this contact's
 * data" is decided by matching the address, not a foreign key.
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
     * what gets written into it. An Attribute mutator, not merely a
     * convention every write site has to remember: `insertOrIgnore()`
     * (SignUpForPrerelease's own write path) bypasses Eloquent entirely and
     * still calls normalizeEmail() itself, but any future `create()`/
     * `update()` on this model now normalises structurally rather than by
     * whoever wrote it remembering to.
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value) => self::normalizeEmail($value));
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
