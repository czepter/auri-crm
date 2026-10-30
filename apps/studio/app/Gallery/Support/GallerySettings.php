<?php

namespace App\Gallery\Support;

use App\Gallery\Models\GalleryPreset;
use App\Models\StudioSettings;

final class GallerySettings
{
    /**
     * P-09: what a new gallery starts as, so a studio stops setting it per delivery.
     *
     * @return array{expiryDays: ?int}
     */
    public static function defaults(): array
    {
        return ['expiryDays' => StudioSettings::current()->gallery_default_expiry_days];
    }

    /**
     * G-15: die vier Gestaltungsschluessel, mit denen eine neue Galerie
     * geboren wird. Ohne Vorgabe die eingebauten Werte — GalleryPreset::DESIGN
     * ist die eine Stelle, die sie nennt.
     *
     * @return array<string, string>
     */
    public static function design(): array
    {
        return [...GalleryPreset::DESIGN, ...(StudioSettings::current()->gallery_default_design ?? [])];
    }
}
