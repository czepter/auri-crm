<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Foto, das ein Portal-Kunde für sich ausgeblendet hat. An der Galerie
 * und nicht an der Fassung, aus demselben Grund wie GalleryList: eine neue
 * Fassung soll die Auswahl nicht verwerfen. Gäste blenden nie aus.
 */
class AssetHide extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = ['gallery_id', 'asset_key', 'contact_id'];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
