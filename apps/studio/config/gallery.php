<?php

/**
 * How long a published gallery stays up, in days. Null means indefinitely,
 * which is what every gallery published before this config existed is, and
 * stays the default until a studio asks for a limit.
 *
 * Config rather than a studio setting: the settings store is a screen, a
 * form, a resolver and a section of de.json for one nullable integer nobody
 * has asked to edit. When a studio asks, the value moves and Gallery::publish
 * does not change.
 */
return [
    'retention_days' => env('GALLERY_RETENTION_DAYS') === null ? null : (int) env('GALLERY_RETENTION_DAYS'),
];
