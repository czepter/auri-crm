<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One email gate attempt. Not Contact-scoped, unlike ClientAccess — a gallery
 * guest proves they control an inbox, not that it belongs to a known client,
 * so anyone holding the gallery link may request one for any address.
 *
 * Same shape ClientAccess established: the token stored as a hash, single
 * use, short-lived.
 */
class GalleryEmailVerification extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    public const VALID_FOR_MINUTES = 30;

    protected $fillable = ['gallery_id', 'email', 'token_hash', 'code_hash', 'expires_at'];

    protected $hidden = ['token_hash', 'code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function scopeLive(Builder $query): void
    {
        $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    /**
     * The live row a raw token opens for this gallery, or null. Scoped to the
     * gallery on top of the token hash itself — belt and braces, since a
     * token minted for one gallery has no business verifying a guest against
     * another even though the hash alone is already effectively unique.
     */
    public static function forToken(Gallery $gallery, string $token): ?self
    {
        return static::query()->live()
            ->where('gallery_id', $gallery->id)
            ->where('token_hash', self::hash($token))
            ->first();
    }
}
