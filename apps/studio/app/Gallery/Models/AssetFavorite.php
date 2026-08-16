<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Gallery\AssetFavoriteFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One guest's favorite mark on one asset within one GalleryVersion (G-09).
 *
 * guest_identifier is the portal session's own id for this visitor, minted on
 * first use (GalleryEngagementController). It carries no account: a signed-in
 * client's marks are a GalleryList owned by their Contact instead (P-06,
 * #225), because a value that dies with a cookie cannot carry "Eure Auswahl"
 * across two devices.
 *
 * The (tenant, version, key, guest) unique index is what keeps the toggle
 * race-safe without it having to serialize the request itself.
 */
class AssetFavorite extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['gallery_version_id', 'asset_key', 'guest_identifier'];

    public function galleryVersion(): BelongsTo
    {
        return $this->belongsTo(GalleryVersion::class);
    }

    protected static function newFactory(): Factory
    {
        return AssetFavoriteFactory::new();
    }
}
