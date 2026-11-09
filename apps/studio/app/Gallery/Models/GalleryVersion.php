<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Media\Models\ZipBuild;
use Database\Factories\Gallery\GalleryVersionFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One exact, immutable publish of a Gallery — a snapshot of title/assets at
 * the moment Gallery::publish() ran. Never updated after creation: a guest
 * (or a cache, per G-08) already pointing at this row must keep seeing
 * exactly what it saw the day it was published, no matter how many times the
 * gallery's draft state is edited and republished afterwards.
 */
class GalleryVersion extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['gallery_id', 'version', 'title', 'assets', 'design', 'cover_key', 'published_at'];

    protected function casts(): array
    {
        return [
            'assets' => 'array',
            'design' => 'array',
            'published_at' => 'immutable_datetime',
        ];
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    /**
     * Die Bilder, die diese Zielgruppe sieht: Gäste nur aus Sets mit
     * Zugang "guests". Ein Bild ohne bekanntes Set (ältere Fassung) bleibt
     * sichtbar.
     *
     * @return array<int, array<string, mixed>>
     */
    public function assetsFor(bool $client): array
    {
        $assets = $this->assets ?? [];

        if ($client) {
            return $assets;
        }

        $internal = $this->gallery->sets()->where('access', GallerySet::INTERNAL)->pluck('name')->all();

        return array_values(array_filter(
            $assets,
            fn (array $asset) => ! in_array($asset['set'] ?? null, $internal, true),
        ));
    }

    public function sees(string $key, bool $client): bool
    {
        return collect($this->assetsFor($client))->contains(fn (array $asset) => $asset['key'] === $key);
    }

    /** Every (scope, size) archive ever built for this version — G-10, deduped one row per triple. */
    public function zipBuilds(): HasMany
    {
        return $this->hasMany(ZipBuild::class);
    }

    /** G-09: every guest mark against this version's assets, whichever guest made it. */
    public function assetFavorites(): HasMany
    {
        return $this->hasMany(AssetFavorite::class);
    }

    protected static function newFactory(): Factory
    {
        return GalleryVersionFactory::new();
    }
}
