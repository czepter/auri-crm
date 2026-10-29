<?php

namespace App\Media\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * G-08: the widths and formats the gallery grid is allowed to ask for, plus
 * the GD resize/encode itself. GD ships with the `gd` extension this app
 * already requires (composer.json, CI) and covers AVIF/WebP encoding
 * natively as of PHP 8.1 — no Intervention Image, no second image library
 * for what one already-installed extension does.
 *
 * The encode happens once per width and format, keyed by the original's own
 * object key rather than by the GalleryVersion or Gallery asking for it: two
 * different requests for the same original at the same width and format —
 * a published version and its own still-open draft, or two versions that
 * both kept the picture — decode and encode exactly once between them. G-01
 * (#229) is what set the target this had been waiting for — a filmstrip of
 * 33 previews re-decoding 33 originals on every open.
 */
class ImageVariant
{
    /** @var list<int> */
    public const WIDTHS = [400, 800, 1200, 1600, 2400];

    /** @var list<string> */
    public const FORMATS = ['avif', 'webp', 'jpeg'];

    public static function isAllowed(int $width, string $format): bool
    {
        return in_array($width, self::WIDTHS, true) && in_array($format, self::FORMATS, true);
    }

    public static function mimeType(string $format): string
    {
        return "image/{$format}";
    }

    /** Resizes to $width (never upscaled) and encodes as $format. */
    public static function resizeAndEncode(string $bytes, int $width, string $format): string
    {
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            throw new RuntimeException('Not a decodable image.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $targetWidth = min($width, $sourceWidth);
        $targetHeight = max(1, (int) round($sourceHeight * ($targetWidth / $sourceWidth)));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        // jpeg has no alpha channel — leaving these on would matte transparent
        // pixels to black instead of the flattened background jpeg expects.
        if ($format !== 'jpeg') {
            imagealphablending($target, false);
            imagesavealpha($target, true);
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        // No imagedestroy() calls: a no-op since PHP 8.0 (GD images are
        // refcounted like any other value) and deprecated outright in 8.5.
        ob_start();
        match ($format) {
            'avif' => imageavif($target),
            'webp' => imagewebp($target),
            'jpeg' => imagejpeg($target, quality: 82),
        };
        $encoded = ob_get_clean();

        if ($encoded === false || $encoded === '') {
            throw new RuntimeException("Failed to encode image as {$format}.");
        }

        return $encoded;
    }

    /**
     * The encoded bytes for $key at $width/$format, from the `s3` disk if
     * this combination has been asked for before, otherwise produced and
     * cached there for next time. Callers own confirming that $key is one
     * this request is allowed to see — this only knows how to fetch it.
     *
     * No lock: two requests racing on the same variant both write the same
     * bytes to the same key.
     */
    public static function deliver(string $key, int $width, string $format): string
    {
        $disk = Storage::disk('s3');
        $cached = "variants/{$width}/{$format}/{$key}.{$format}";

        if ($disk->exists($cached)) {
            return $disk->get($cached);
        }

        abort_unless($disk->exists($key), 404);

        $variant = self::resizeAndEncode($disk->get($key), $width, $format);
        $disk->put($cached, $variant);

        return $variant;
    }
}
