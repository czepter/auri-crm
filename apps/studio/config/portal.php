<?php

/**
 * Where the client surface answers, as seen from the outside.
 *
 * Client-facing links built by the portal process itself (RequestClientAccess,
 * RequestGalleryEmailVerification) use url() and already know their own
 * APP_URL. This value is for the links the ADMIN process composes onto the
 * portal — P-09's "preview as client", QuoteSent's quote and unsubscribe
 * links — where the container's own APP_URL is the studio's.
 *
 * Falls back to APP_URL for a single-host setup, which is what the test
 * suite runs as.
 */
$url = rtrim((string) env('PORTAL_URL', env('APP_URL', 'http://localhost')), '/');

// Coolify's ${SERVICE_FQDN_PORTAL} is the bare host; without a scheme every
// link composed onto it resolves relative to the page it sits on.
return [
    'url' => preg_match('#^https?://#', $url) ? $url : "https://{$url}",
];
