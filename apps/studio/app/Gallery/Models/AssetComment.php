<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Database\Factories\Gallery\AssetCommentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One guest's comment on one asset within one GalleryVersion (G-12). Same
 * guest-identity shape as AssetFavorite — guest_identifier carries no
 * account behind it, see AssetCommentController's own doc comment.
 */
class AssetComment extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['gallery_version_id', 'asset_key', 'guest_identifier', 'body'];

    public function galleryVersion(): BelongsTo
    {
        return $this->belongsTo(GalleryVersion::class);
    }

    protected static function newFactory(): Factory
    {
        return AssetCommentFactory::new();
    }
}
