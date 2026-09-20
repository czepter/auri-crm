<?php

namespace App\Support;

class LocalPath
{
    /**
     * A path on this domain from user input, or null. One leading slash, not
     * `//` or `/\` (both read as another host), and no control characters
     * (a browser drops a tab or newline from a URL, so `/<tab>/host` is `//host`).
     */
    public static function from(mixed $value): ?string
    {
        return is_string($value)
            && preg_match('#^/(?![/\\\\])#', $value) === 1
            && preg_match('/[\x00-\x1f\x7f]/', $value) === 0
                ? $value
                : null;
    }
}
