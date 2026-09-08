<?php

namespace App\Gallery\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
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
     * oder gar nicht: wer design setzt, setzt alle vier Schluessel, sonst
     * traegt eine Galerie eine halbe Gestaltung und der Rest kommt aus dem
     * Stylesheet.
     */
    public const DESIGN = ['cover' => 'full', 'type' => 'serif', 'colour' => 'light', 'grid' => 'wide'];

    protected $fillable = [
        'name', 'design', 'expiry_mode', 'expiry_days', 'is_default',
        'requires_settled_balance', 'downloads_enabled', 'lists_enabled', 'comments_enabled',
    ];

    protected function casts(): array
    {
        return [
            'design' => 'array',
            'requires_settled_balance' => 'boolean',
            'downloads_enabled' => 'boolean',
            'lists_enabled' => 'boolean',
            'comments_enabled' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    protected static function newFactory(): Factory
    {
        return GalleryPresetFactory::new();
    }
}
