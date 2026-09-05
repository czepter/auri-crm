<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pending switch of the address a staff member signs in with. The link
 * goes to the NEW address and nothing is written until it is followed —
 * see App\Portal\Models\EmailChange, which does the same for a client.
 */
class UserEmailChange extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    public const VALID_FOR_HOURS = 24;

    protected $fillable = ['user_id', 'new_email', 'token_hash', 'expires_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeLive(Builder $query): void
    {
        $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Uniform for expired, already used and never existed. */
    public static function forToken(string $token): ?self
    {
        return static::query()->live()->where('token_hash', self::hash($token))->first();
    }
}
