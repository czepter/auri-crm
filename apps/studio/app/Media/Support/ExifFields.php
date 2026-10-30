<?php

namespace App\Media\Support;

use Illuminate\Support\Carbon;

/**
 * The handful of EXIF fields cheap to store on an Asset (#52: "whatever's
 * cheap to store"), pulled from PHP's built-in exif_read_data() — no
 * composer.json package exists for this, and ext-exif already ships in
 * docker/php/Dockerfile and CI's shivammathur/setup-php extension list.
 *
 * The documented gap: exif_read_data() only understands JPEG and TIFF.
 * HEIC/AVIF captures, PNG exports and RAW formats never reach this class at
 * all — App\Gallery\Actions\IngestCompletedUpload only calls it after
 * confirming the source is a JPEG via exif_imagetype(), and every other
 * format simply carries no camera metadata on its Asset row. Dimensions
 * still come from libvips regardless of format; only taken_at/camera_make/
 * camera_model are JPEG-only.
 *
 * A pure function over exif_read_data()'s own array shape rather than over
 * a file path, so it is testable without a real EXIF-bearing fixture (GD,
 * this repo's only way to synthesize a JPEG in a test, cannot write EXIF).
 */
class ExifFields
{
    /**
     * @param  array<string, mixed>  $raw  exif_read_data()'s own return value
     * @return array{takenAt: ?Carbon, cameraMake: ?string, cameraModel: ?string}
     */
    public static function fromReadData(array $raw): array
    {
        $exif = $raw['EXIF'] ?? [];
        $ifd0 = $raw['IFD0'] ?? [];

        $dateTime = $exif['DateTimeOriginal'] ?? $raw['DateTimeOriginal'] ?? null;

        return [
            // "YYYY:MM:DD HH:MM:SS" — EXIF's own date format, not ISO 8601.
            'takenAt' => is_string($dateTime) ? self::parseDateTime($dateTime) : null,
            'cameraMake' => self::stringOrNull($ifd0['Make'] ?? $raw['Make'] ?? null),
            'cameraModel' => self::stringOrNull($ifd0['Model'] ?? $raw['Model'] ?? null),
        ];
    }

    private static function parseDateTime(string $value): ?Carbon
    {
        try {
            return Carbon::createFromFormat('Y:m:d H:i:s', $value) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function stringOrNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
