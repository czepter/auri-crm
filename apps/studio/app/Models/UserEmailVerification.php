<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEmailVerification extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    public const VALID_FOR_MINUTES = 30;

    public const MAX_ATTEMPTS = 5;

    protected $fillable = ['user_id', 'token_hash', 'code_hash', 'expires_at'];

    protected $hidden = ['token_hash', 'code_hash'];

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

    public static function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    /** Uniform for expired, already used and never existed. */
    public static function forToken(string $token): ?self
    {
        return static::query()->live()->where('token_hash', self::hash($token))->first();
    }

    /** The current live record for this user, if any — attempts are checked against it by the caller. */
    public static function forUser(User $user): ?self
    {
        return static::query()->live()->where('user_id', $user->id)->latest('id')->first();
    }

    public function matchesCode(string $code): bool
    {
        return hash_equals($this->code_hash, self::hash($code));
    }
}
