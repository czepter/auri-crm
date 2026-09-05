<?php

namespace App\Documents\Support;

/**
 * A template body, flattened for a plain-text composer.
 *
 * T-12's editor writes HTML, and E-06's `messages.body` is a text column
 * rendered as text. Handing the editor's markup to that composer would put
 * `<p>` tags in front of a client, so the one place the two meet converts
 * rather than pretending they are the same thing.
 *
 * Deliberately not a general HTML-to-Markdown pass: the block set a mail
 * template may use (DocumentBlockCatalog) is paragraphs, headings, lists,
 * quotes and tables, and everything a studio actually types into a client
 * mail survives block boundaries becoming line breaks.
 */
class PlainTextBody
{
    public static function fromHtml(string $html): string
    {
        // Block boundaries become line breaks before the tags go, or every
        // paragraph in the template would run into the next one as one
        // unbroken sentence. Paragraphs and headings get a blank line
        // between them, the way they read on screen; list rows and explicit
        // breaks get a single one, because a bulleted list with a gap
        // between every item is not what the template author drew.
        $withBreaks = preg_replace(
            ['#</(p|div|h[1-6]|blockquote)>#i', '#<br\s*/?>#i', '#</(li|tr)>#i'],
            ["\n\n", "\n", "\n"],
            $html,
        ) ?? $html;

        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse the runs the conversion itself creates (a </li></ul> pair
        // yields two), and trim the trailing one every body ends with.
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
