<?php

namespace App\Settings\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Das gespeicherte Logo, ausgeliefert von beiden Oberflächen.
 *
 * Eine SVG-Datei ist Markup und läuft, wenn der Browser sie als Seite öffnet,
 * auf unserer Herkunft — ein <script> darin hätte die Sitzung des Studios.
 * Deshalb liegt die Auslieferung hier und nicht zweimal im Controller: die
 * beiden Kopfzeilen sind die Bedingung dafür, dass SVG überhaupt hochgeladen
 * werden darf, und dürfen nicht an einer Stelle fehlen.
 */
final class LogoResponse
{
    public static function for(string $path): StreamedResponse
    {
        return Storage::disk('local')->response($path, headers: [
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
