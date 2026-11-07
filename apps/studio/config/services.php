<?php

// Coolifys SERVICE_FQDN_* liefert nicht zuverlässig ein Schema mit — sobald
// eine Anwendung mehrere Domains trägt, kommt hier auch mal ein nacktes
// "host.tld" an. RFC 9728 verlangt absolute URLs für 'api'/'admin'/'mcp'
// unten, also erzwingt dieser Helfer eins, statt dem Wert blind zu
// vertrauen.
$withScheme = fn (?string $url): ?string => $url && ! str_contains($url, '://')
    ? "https://{$url}"
    : $url;

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // The `gotenberg` Compose service converts a rendered document's HTML to
    // PDF. The default already matches docker-compose.yml's service
    // name and Gotenberg's own default port, so a Compose stack needs no
    // override — only a deploy environment naming the service differently
    // does. See App\Support\GotenbergClient.
    'gotenberg' => [
        'url' => env('GOTENBERG_URL', 'http://gotenberg:3000'),
    ],

    // G-05: apps/gallery is the guest's own delivery viewer — a separate
    // Astro process (docker-compose.yml's `gallery` service, default port
    // matching GALLERY_PORT there) — never the studio host this app itself
    // answers on. Gallery::publicUrl() is the only place this is read.
    // Coolify's ${SERVICE_FQDN_GALLERY_4321} is the bare host; without a
    // scheme publicUrl() resolves relative to the current surface.
    'gallery' => [
        'url' => rtrim((string) $withScheme(env('GALLERY_URL') ?: 'http://localhost:4321'), '/'),
    ],

    // Die anderen zwei Surfaces, wie sie von außen erreichbar sind. Der
    // Admin-Container kennt nur seine eigene APP_URL; für die Hilfeartikel
    // zum Anfrageformular und zur MCP-Verbindung ist das der falsche Host.
    // Fallback auf APP_URL für den Einzelhost-Betrieb, den die Testsuite
    // fährt — dieselbe Form wie config/portal.php. `?:` statt eines
    // env()-Defaults, weil .env.example beide Schluessel leer mitbringt und
    // ein leerer String den Default nie ausloest.
    'api' => [
        'url' => rtrim((string) $withScheme(env('API_URL') ?: env('APP_URL', 'http://localhost')), '/'),
    ],

    // Der Admin-Container ist der OAuth-Aussteller für die MCP-Verbindung,
    // und das Dokument, das darauf zeigt, liefert das api-Surface
    // (OAuthProtectedResourceController) — das braucht die Adresse des
    // anderen Containers.
    'admin' => [
        'url' => rtrim((string) $withScheme(env('ADMIN_URL') ?: env('APP_URL', 'http://localhost')), '/'),
    ],

    // Die MCP-Adresse ist eine Route des api-Surface, keine eigene
    // Bereitstellung mehr; MCP_URL bleibt für den Fall, dass ein Proxy sie
    // anders veröffentlicht.
    'mcp' => [
        'url' => env('MCP_URL')
            ? rtrim((string) $withScheme(env('MCP_URL')), '/')
            : rtrim((string) $withScheme(env('API_URL') ?: env('APP_URL', 'http://localhost')), '/').'/mcp',
        // Der Bearer, mit dem der stdio-Transport das api-Surface ruft; über
        // HTTP kommt er stattdessen aus der Sitzung selbst (ApiCall).
        'token' => env('MCP_API_TOKEN'),
    ],

    // #357: der Mercure-Hub im Admin-Container. `url` ist, wohin jeder
    // Container publiziert, `public_url`, wo der Browser zuhört. `?: null`
    // aus demselben Grund wie oben; ohne `url` publiziert nichts.
    'mercure' => [
        'url' => env('MERCURE_URL') ?: null,
        'public_url' => env('MERCURE_PUBLIC_URL') ?: '/.well-known/mercure',
        // Der Hub im Portal-Container: eigener Host, also eigenes Cookie.
        'portal_url' => env('MERCURE_PORTAL_URL') ?: null,
        'portal_public_url' => env('MERCURE_PORTAL_PUBLIC_URL') ?: '/.well-known/mercure',
        'publisher_key' => env('MERCURE_PUBLISHER_JWT_KEY') ?: null,
        'subscriber_key' => env('MERCURE_SUBSCRIBER_JWT_KEY') ?: null,
    ],

    // M-05: verifies App\Payments\Http\Controllers\StripeWebhookController's
    // inbound deliveries. One deployment-wide value, the same shape as every
    // credential above it on this file — not a studio-editable pair like
    // StudioSettings' own test/live Stripe key pair (T-07), because nothing
    // in this codebase resolves a per-request tenant yet (config/tenancy.php:
    // "one studio today"), so a second webhook endpoint with its own secret
    // per tenant has nothing to be scoped to.
    //
    // ponytail: one secret for whichever mode's events actually arrive here,
    // not a test/live pair — add the split if a studio ever needs both a
    // live endpoint and a standing test endpoint receiving events at once.
    // Der Dienst, der die Versionsliste hält: liest die Tags des Repos und
    // liefert sie als JSON (release-log/server.mjs). Ohne die Variable
    // bleiben die Neuigkeiten-Screens leer — eine Instanz ohne release-log
    // ist eine funktionierende Instanz, nur eine ohne diesen Screen.
    'release_log' => [
        'url' => env('RELEASE_LOG_URL'),
    ],

    'stripe' => [
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

];
