<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Documents\Models\DocumentTemplate;
use App\Gallery\Support\GalleryPermissions;
use Database\Factories\Gallery\GalleryPresetFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Womit eine neue Galerie geboren wird — und, seit "Vorlage anwenden"
 * (GalleryPresetController::applyToGallery), womit eine bestehende Galerie
 * ihr Design und ihre Angebote überschreiben lässt. Gelesen wird die
 * Vorlage bei jeder Anwendung neu, nie kopiert und vergessen.
 */
class GalleryPreset extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    public const MODES = ['inherit', 'days', 'never'];

    /**
     * Womit eine Galerie gestaltet ist, wenn niemand etwas gesagt hat. Ganz
     * oder gar nicht: wer design setzt, setzt alle sechs Schluessel, sonst
     * traegt eine Galerie eine halbe Gestaltung und der Rest kommt aus dem
     * Stylesheet.
     */
    public const DESIGN = [
        'cover' => 'zentriert', 'type' => 'serif', 'colour' => 'hell',
        'size' => 'mittel', 'spacing' => 'eng', 'flow' => 'zeilen',
    ];

    protected $fillable = [
        'name', 'design', 'expiry_mode', 'expiry_days', 'is_default',
        'downloads_enabled', 'lists_enabled', 'comments_enabled',
        'sets', 'view_mode', 'download_rule', 'download_sizes', 'permissions', 'intro_enabled', 'expiry_reminder',
        'mail_template_family_id',
    ];

    protected function casts(): array
    {
        return [
            'design' => 'array',
            'downloads_enabled' => 'boolean',
            'lists_enabled' => 'boolean',
            'comments_enabled' => 'boolean',
            'is_default' => 'boolean',
            'sets' => 'array',
            'download_sizes' => 'array',
            'permissions' => 'array',
            'intro_enabled' => 'boolean',
            'expiry_reminder' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Die alten Schalter bleiben als Summe der neuen stehen, wie auf der
        // Galerie, damit galleryBirth.ts weiter einen Wert liest.
        static::saving(function (GalleryPreset $preset) {
            if ($preset->download_rule !== null) {
                $preset->downloads_enabled = $preset->download_rule !== 'off';
            }

            if ($preset->permissions !== null) {
                $permissions = GalleryPermissions::normalize($preset->permissions);
                $preset->permissions = $permissions;
                $preset->lists_enabled = $permissions['client']['favorites'] || $permissions['guest']['favorites'];
                $preset->comments_enabled = $permissions['client']['comments'] || $permissions['guest']['comments'];
            }
        });
    }

    /** Die aktuelle Fassung der Mail-Vorlage; null, wenn keine gewählt oder ihre Familie weg ist. */
    public function mailTemplate(): ?DocumentTemplate
    {
        return $this->mail_template_family_id === null
            ? null
            : DocumentTemplate::current()->where('family_id', $this->mail_template_family_id)->first();
    }

    /**
     * Was die Vorlage einer neuen Galerie mitgibt. Offen Gelassenes (null)
     * fehlt, damit die Ebene darunter stehen bleibt.
     *
     * @return array<string, mixed>
     */
    public function birthAttributes(): array
    {
        $attributes = $this->only([
            'design', 'downloads_enabled', 'lists_enabled', 'comments_enabled',
            'view_mode', 'download_rule', 'download_sizes', 'permissions', 'intro_enabled', 'expiry_reminder',
        ]);

        // Vorlagen von vor der Regel kennen nur den Schalter.
        $attributes['download_rule'] ??= match ($this->downloads_enabled) {
            true => 'open',
            false => 'off',
            null => null,
        };

        return array_filter($attributes, fn (mixed $value) => $value !== null);
    }

    protected static function newFactory(): Factory
    {
        return GalleryPresetFactory::new();
    }
}
