<?php

namespace App\Portal\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P-05 (#224). A pending switch of the address a client signs in with.
 *
 * The same shape ClientAccess established — token hashed, compared, never
 * read back — with one difference that is the whole point: the link goes to
 * the NEW address, and nothing is written to the contact until it is
 * followed. A typo in this field would otherwise lock a client out of the
 * only way back into their own portal.
 *
 * A day rather than fifteen minutes: this is not a credential that signs
 * anybody in, and the person following it may have to go and find the other
 * inbox first.
 */
class EmailChange extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    public const VALID_FOR_HOURS = 24;

    protected $table = 'contact_email_changes';

    protected $fillable = ['contact_id', 'new_email', 'token_hash', 'expires_at'];

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

    /** Uniform for expired, already used and never existed — same rule as ClientAccess. */
    public static function forToken(string $token): ?self
    {
        return static::query()->live()->where('token_hash', self::hash($token))->first();
    }
}
