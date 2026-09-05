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

    /**
     * Findet oder legt den Gast dieser Galerie an. Drei Wege führen hierher —
     * der Code im Betrachter, das Mail-Tor des Portals und die Zustelladresse
     * beim Download —, und alle drei meinen dieselbe Zeile.
     */
    public static function forGallery(Gallery $gallery, string $email, ?string $name = null): self
    {
        $email = mb_strtolower(trim($email));
        $guest = self::query()->firstOrNew(['gallery_id' => $gallery->id, 'email' => $email]);

        $guest->forceFill([
            'name' => $name ?? $guest->name ?? self::nameFromEmail($email),
            'identifier' => self::identifierFor($gallery, $email),
        ])->save();

        return $guest;
    }

    /**
     * Ein Namensvorschlag aus dem Teil vor dem @, für den Weg, auf dem
     * niemand nach einem Namen gefragt wurde — das Portal kennt nur die
     * Adresse. `anna.mueller1987@…` wird `Anna Mueller`.
     */
    public static function nameFromEmail(string $email): string
    {
        $local = mb_strstr($email, '@', true) ?: $email;

        $words = collect(preg_split('/[._+-]+/', $local) ?: [])
            ->map(fn (string $part) => preg_replace('/\d+$/', '', $part))
            ->filter()
            ->map(fn (string $part) => mb_convert_case($part, MB_CASE_TITLE));

        return $words->isEmpty() ? $local : $words->implode(' ');
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}
