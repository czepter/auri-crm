<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Enums\StaffRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pending seat at the studio.
 *
 * The token is held as a hash and compared, never read back — the raw value
 * lives only in the link that leaves the server.
 */
class StaffInvitation extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /** Long enough to be worth living in an inbox, short enough to expire. */
    public const VALID_FOR_DAYS = 7;

    // accepted_at is fillable so re-inviting can clear it: superseding a live
    // invitation writes the same row, and a stale acceptance stamp there would
    // make the new link look already spent.
    protected $fillable = [
        'email',
        'name',
        'role',
        'token_hash',
        'expires_at',
        'accepted_at',
        'invited_by_id',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'role' => StaffRole::class,
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
        ];
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    /**
     * Finds the invitation a raw token opens, or null.
     *
     * Hashed with SHA-256 rather than bcrypt so the lookup is a single indexed
     * query: the token is 32 random bytes from the server, not a human-chosen
     * secret, so there is nothing here for a slow hash to defend against.
     */
    public static function forToken(string $token): ?self
    {
        return static::query()->pending()->where('token_hash', self::hash($token))->first();
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Turns the invitation into a real account. The password is the one the
     * invited person just chose; MFA is deliberately left unset, so the very
     * next request meets the enrollment gate.
     */
    public function accept(string $password): User
    {
        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $password,
            'role' => $this->role,
        ]);

        $this->forceFill(['accepted_at' => now()])->save();

        return $user;
    }
}
