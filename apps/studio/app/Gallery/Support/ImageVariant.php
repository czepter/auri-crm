<?php

namespace App\Gallery\Support;

use RuntimeException;

/**
 * G-08: the widths and formats the gallery grid is allowed to ask for, plus
 * the GD resize/encode itself. GD ships with the `gd` extension this app
 * already requires (composer.json, CI) and covers AVIF/WebP encoding
 * natively as of PHP 8.1 — no Intervention Image, no second image library
 * for what one already-installed extension does.
 *
 * ponytail: every request re-decodes the original and re-encodes the
 * variant — no derivative cache. G-01a's own docs/media.md says as much for
 * the wider caching question: "Set a target when there is a real gallery and
 * a real measurement to set it against." Upgrade path, if origin CPU turns
 * out to matter: cache the encoded bytes back onto the `s3` disk under the
 * same immutable path the request came in on, so the next request for that
 * width/format is a Storage::get, not a re-encode.
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
}
