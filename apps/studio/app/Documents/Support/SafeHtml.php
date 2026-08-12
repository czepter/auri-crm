<?php

namespace App\Documents\Support;

/**
 * The server-side half of the href check the serializer also performs in the
 * browser. The browser half keeps a template author from pasting a bad link
 * in by accident; this half is the one that actually holds, because `body`
 * arrives as a POST field and need never have passed through the editor.
 *
 * It matters most for mail: a Contract or Quote becomes a PDF we render
 * ourselves, but a mail body is HTML handed to an inbox we never render, so
 * a javascript:/data: href in it is a link someone else's client decides
 * what to do with.
 *
 * Relative hrefs count as unsafe: a document is a PDF or an email, with no
 * site for "/intern/kunden" to be relative to, so one is a mistake rather
 * than a link.
 */
class SafeHtml
{
    /** Matches href in all three quoting styles: "x", 'x', and bare. */
    private const HREF = '/\s+href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i';

    private const SAFE_SCHEME = '/^(https?:|mailto:)/i';

    public static function hasUnsafeHref(string $html): bool
    {
        preg_match_all(self::HREF, $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $url = trim($match[3] ?? '') ?: trim($match[2] ?? '') ?: trim($match[1] ?? '');

            if (preg_match(self::SAFE_SCHEME, $url) !== 1) {
                return true;
            }
        }

        return false;
    }
}
