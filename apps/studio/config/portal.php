<?php

/**
 * Where the client surface answers, as seen from the outside.
 *
 * Every other client-facing link in this app is built with url() by the
 * portal process itself (RequestClientAccess, QuoteSent), which already
 * knows its own APP_URL. P-09's "preview as client" is the first link the
 * ADMIN process has to build onto the portal, and the admin container's
 * APP_URL is its own — hence one value rather than a second guess.
 *
 * Falls back to APP_URL for a single-host setup, which is what the test
 * suite runs as.
 */
return [
    'url' => rtrim((string) env('PORTAL_URL', env('APP_URL', 'http://localhost')), '/'),
];
