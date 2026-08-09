<?php

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

    // M-01: the `gotenberg` Compose service converts a rendered contract's
    // HTML to PDF. The default already matches docker-compose.yml's service
    // name and Gotenberg's own default port, so a Compose stack needs no
    // override — only a deploy environment naming the service differently
    // does. See App\Contracts\Support\GotenbergClient.
    'gotenberg' => [
        'url' => env('GOTENBERG_URL', 'http://gotenberg:3000'),
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
    'stripe' => [
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

];
