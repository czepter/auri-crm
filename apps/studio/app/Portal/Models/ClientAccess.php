<?php

namespace App\Portal\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One magic link. Contact-scoped, single use, and short-lived.
 *
 * The same shape StaffInvitation established — token stored as a hash,
 * compared, never read back — with fifteen minutes instead of seven days and a
 * consumption stamp, because an invitation is used once by arrangement while a
 * sign-in link is used once by rule.
 */
class ClientAccess extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * Long enough to walk to a laptop, short enough that a link forwarded or
     * left in an inbox is worthless by the time anyone else reads it.
     */
    public const VALID_FOR_MINUTES = 15;

    protected $fillable = ['contact_id', 'token_hash', 'expires_at', 'requested_ip', 'preview_by_id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * P-09 (#228): set when a studio member minted this link to look at what
     * a client sees. The session it opens is read-only (EnsureAudience) and
     * the audit trail names them.
     */
    public function previewBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preview_by_id');
    }

    public function isPreview(): bool
    {
        return $this->preview_by_id !== null;
    }

    public function isLive(): bool
    {
        return $this->consumed_at === null
            && $this->expires_at->isFuture()
            && $this->contact?->portal_access_revoked_at === null;
    }

    /**
     * Revocation lives on the contact, not scattered across every row it
     * ever issued — a revoked contact's rows go dead by construction of this
     * query rather than by rewriting them one by one (A-04). Consumed and
     * expired keep meaning exactly what they already meant.
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->whereHas('contact', fn (Builder $q) => $q->whereNull('portal_access_revoked_at'));
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * The live link a raw token opens, or null.
     *
     * Deliberately does not distinguish "expired" from "already used" from
     * "never existed": all three mean the same thing to whoever is holding the
     * link, and saying which would tell someone probing that they had at least
     * found a real one.
     */
    public static function forToken(string $token): ?self
    {
        return static::query()->live()->where('token_hash', self::hash($token))->first();
    }
}
