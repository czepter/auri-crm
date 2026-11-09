<?php

namespace App\Gallery\Support;

use App\Gallery\Models\Gallery;
use App\Media\Support\Watermark;
use App\Models\StudioSettings;
use Illuminate\Support\Facades\Storage;

/**
 * Was über die Vorschaubilder gelegt wird, die Gäste und Kunden bekommen:
 * null für eine Galerie ohne Wasserzeichen. Originale und die Vorschau des
 * Studios fragen hier nie an.
 */
final class GalleryWatermark
{
    public static function for(Gallery $gallery): ?Watermark
    {
        if (! in_array($gallery->view_mode, ['text', 'logo'], true)) {
            return null;
        }

        $studio = StudioSettings::current();
        $text = trim((string) ($gallery->watermark_text ?: $studio->display_name));

        $path = $gallery->view_mode === 'logo' ? $studio->logo_path : null;
        $logo = $path !== null && ! str_ends_with($path, '.svg') && Storage::disk('local')->exists($path)
            ? Storage::disk('local')->get($path)
            : null;

        return new Watermark($text, $logo);
    }
}
