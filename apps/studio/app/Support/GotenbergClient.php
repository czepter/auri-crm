<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * The one place that talks to the `gotenberg` Compose service (M-01;
 * docker-compose.yml). Chromium's HTML-to-PDF conversion form takes a
 * multipart upload named `index.html` — see
 * https://gotenberg.dev/docs/routes#html-file-into-pdf-route.
 *
 * Nothing else in the app calls Http against Gotenberg directly, which is
 * what lets a test mock this one boundary (Http::fake()) rather than every
 * caller of it. Lives beside AuditLogger in App\Support rather than under
 * a domain: quotes, invoices and E-04's timeline PDF all call it.
 */
class GotenbergClient
{
    public function __construct(private readonly string $baseUrl = '') {}

    /** `$preferCssPageSize` lets the document's own `@page` set paper size and margins; Chromium defaults to Letter. */
    public function htmlToPdf(string $html, bool $preferCssPageSize = false): string
    {
        $url = $this->baseUrl ?: config('services.gotenberg.url');

        return Http::asMultipart()
            ->attach('files', $html, 'index.html')
            ->post(rtrim($url, '/').'/forms/chromium/convert/html', $preferCssPageSize ? ['preferCssPageSize' => 'true'] : [])
            ->throw()
            ->body();
    }
}
