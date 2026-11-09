<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Contact;
use App\Models\User;
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

    protected $fillable = ['gallery_version_id', 'asset_key', 'guest_identifier', 'body', 'contact_id', 'user_id'];

    /**
     * G-01 (#229): set when a signed-in client did this, so the studio reads
     * a name rather than "Gast". Null for an actual guest, which is most of
     * them — guest_identifier stays required either way.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** Gesetzt, wenn das Studio geantwortet hat; dann fehlt guest_identifier. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function galleryVersion(): BelongsTo
    {
        return $this->belongsTo(GalleryVersion::class);
    }

    protected static function newFactory(): Factory
    {
        return AssetCommentFactory::new();
    }
}
