<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * P-06 (#225). One selection a client made inside one gallery — "Favoriten",
 * "Album", "Für die Eltern", or the unnamed one behind the heart.
 *
 * Owned by a Contact, not by a guest identifier. That is the whole
 * difference from AssetFavorite, which hangs off a string minted in a
 * browser's localStorage and, by its own docblock, "carries no account
 * behind it": under that identity "Eure Auswahl" would show every guest's
 * hearts mixed together. A signed-in client never writes an AssetFavorite;
 * their heart is the unnamed list here.
 *
 * Attached to the gallery rather than to a GalleryVersion — see the
 * migration for why a re-publish must not orphan an evening's work.
 */
class GalleryList extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = ['gallery_id', 'contact_id', 'name'];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GalleryListItem::class)->orderBy('position')->orderBy('id');
    }

    /** The heart: one per client per gallery, made on first use rather than at sign-in. */
    public static function unnamedFor(Gallery $gallery, Contact $contact): self
    {
        return static::query()->firstOrCreate([
            'gallery_id' => $gallery->id,
            'contact_id' => $contact->id,
            'name' => null,
        ]);
    }
}
