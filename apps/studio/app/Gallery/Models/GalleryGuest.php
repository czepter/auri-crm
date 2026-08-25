<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Mensch in einer Galerie. Kein Konto — eine bestätigte Adresse und der
 * Name, der unter seinen Kommentaren steht.
 *
 * `identifier` ist ein Hash der Adresse und nichts Zufälliges: dieselbe
 * Adresse ergibt ihn auf dem Telefon wie am Rechner, und genau das lässt den
 * Verlauf überall wieder auftauchen. Die Adresse selbst verlässt den Server
 * nie in Richtung anderer Gäste — sichtbar ist der Name.
 */
class GalleryGuest extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    protected $fillable = ['gallery_id', 'email', 'name', 'identifier', 'confirmed_at'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'immutable_datetime'];
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    /**
     * Die Kennung, unter der Herzen und Kommentare liegen.
     *
     * Getrennt je Galerie gehasht: sonst verriete derselbe Wert in zwei
     * Galerien desselben Studios, dass dort derselbe Mensch war.
     */
    public static function identifierFor(Gallery $gallery, string $email): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($email)), $gallery->public_id);
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}
