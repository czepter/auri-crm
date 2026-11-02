<?php

namespace App\Gallery\Support;

use App\Gallery\Models\Gallery;
use Illuminate\Support\Facades\Config;

/**
 * Die PIN-Freigabe des Downloads auf der zustandslosen api-Fläche — was im
 * Portal GalleryAccessSession::DOWNLOAD in der Sitzung ist, reist hier als
 * signiertes Token im Cookie des Betrachters.
 */
class GalleryDownloadPass
{
    public const VALID_FOR_HOURS = 12;

    public function issue(Gallery $gallery): string
    {
        $payload = implode('.', [
            'download',
            $gallery->public_id,
            $gallery->download_pin_version,
            now()->addHours(self::VALID_FOR_HOURS)->timestamp,
        ]);

        return $payload.'.'.$this->sign($payload);
    }

    public function opens(Gallery $gallery, ?string $token): bool
    {
        $parts = explode('.', (string) $token);

        if (count($parts) !== 5 || $parts[0] !== 'download') {
            return false;
        }

        [, $galleryId, $version, $expires, $signature] = $parts;

        if (! hash_equals($this->sign("download.{$galleryId}.{$version}.{$expires}"), $signature)) {
            return false;
        }

        return $galleryId === $gallery->public_id
            && $version === (string) $gallery->download_pin_version
            && (int) $expires > now()->timestamp;
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) Config::get('app.key'));
    }
}
