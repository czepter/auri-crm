<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Contact;
use Database\Factories\Gallery\AssetFavoriteFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One guest's favorite mark on one asset within one GalleryVersion (G-09).
 * guest_identifier carries no account behind it — see AssetFavoriteController's
 * own doc comment for what mints it and why. The (tenant, version, key, guest)
 * unique index is what keeps AssetFavoriteController::toggle() race-safe
 * without it having to serialize the request itself.
 */
class AssetFavorite extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['gallery_version_id', 'asset_key', 'guest_identifier', 'contact_id'];

    /**
     * G-01 (#229): set when a signed-in client did this, so the studio reads
     * a name rather than "Gast". Null for an actual guest, which is most of
     * them — guest_identifier stays required either way.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function galleryVersion(): BelongsTo
    {
        return $this->belongsTo(GalleryVersion::class);
    }

    protected static function newFactory(): Factory
    {
        return AssetFavoriteFactory::new();
    }
}
