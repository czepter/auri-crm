<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The "Herunterladen" mail's own link. Reusable until it expires, unlike
 * ClientAccess or EmailChange — a client returns to the same mail days
 * apart, not once. The archive it leads to is never deleted just because
 * this row goes stale; GalleryDownloadController::prepare() rebuilds one
 * that has, on request, from the download screen itself.
 */
class GalleryDownloadLink extends Model
{
    use BelongsToTenant;

    public const VALID_FOR_DAYS = 7;

    protected $fillable = ['gallery_id', 'token_hash', 'expires_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime'];
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function scopeLive(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** The live link a raw token opens, or null — expired and never-existed look the same. */
    public static function forToken(string $token): ?self
    {
        return static::query()->live()->where('token_hash', self::hash($token))->first();
    }
}
