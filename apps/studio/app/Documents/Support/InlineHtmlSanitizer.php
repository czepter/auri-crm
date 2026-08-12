<?php

namespace App\Documents\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * The write-path half of the editor's inline-HTML allow-list. Editor.js
 * applies each tool's `sanitize` config when it saves inside a live browser
 * editor — which means a `blocks` payload POSTed straight at the controller
 * never meets it. Without this, such a payload is stored verbatim and then
 * re-injected as live HTML into the next staff member's contenteditable when
 * they open the template.
 *
 * The mirror of this list lives in
 * resources/js/admin/documents/blocks/sanitizeInline.ts, which defends the
 * read path. Keep the two in sync; this one is the one that holds.
 *
 * DOM-based rather than regex-based: an allow-list over parsed nodes is the
 * only kind that survives nested and malformed markup.
 */
class InlineHtmlSanitizer
{
    /** tag => allowed attributes. Mirrors DOCUMENT_INLINE in sanitizeInline.ts. */
    private const ALLOW = [
        'b' => [],
        'strong' => [],
        'i' => [],
        'em' => [],
        'u' => [],
        'br' => [],
        'a' => ['href'],
        'span' => ['class', 'data-mf'],
    ];

    private const SAFE_SCHEME = '/^(https?:|mailto:)/i';

    /**
     * Dropped whole, not unwrapped, unlike every other disallowed tag. A
     * template body becomes a PDF or an email a client reads: unwrapping
     * would dump machine source (JS, CSS) into that body as visible prose,
     * which is a defect the author may not notice before sending — whereas
     * a <div> or similar wraps the author's own text, which unwrapping
     * rightly preserves.
     */
    private const DROP_WHOLE = ['script', 'style', 'img', 'iframe', 'object', 'embed'];

    public static function inline(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $document = new DOMDocument;

        // The XML prolog is what stops DOMDocument reading UTF-8 as Latin-1 and
        // turning "Grüße" into mojibake.
        $loaded = @$document->loadHTML(
            '<?xml encoding="utf-8" ?><body>'.$html.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        if ($loaded === false) {
            return '';
        }

        $body = $document->getElementsByTagName('body')->item(0);

        if ($body === null) {
            return '';
        }

        self::clean($body);

        $out = '';

        foreach ($body->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return $out;
    }

    /**
     * Every string leaf in a blocks payload, sanitised. A rawHtml block is
     * skipped: it holds a pre-block-editor body, which is the same
     * staff-authored HTML the old textarea accepted, and no tool renders it
     * into the editor's DOM.
     *
     * @param  array<mixed>  $blocks
     * @return array<mixed>
     */
    public static function blocks(array $blocks): array
    {
        return self::walk($blocks, false);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function walk(array $value, bool $raw): array
    {
        $clean = [];

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $clean[$key] = self::walk($item, $raw || ($item['type'] ?? null) === 'rawHtml');

                continue;
            }

            $clean[$key] = is_string($item) && ! $raw ? self::inline($item) : $item;
        }

        return $clean;
    }

    private static function clean(DOMNode $node): void
    {
        // Snapshot first: the loop mutates the live child list.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            self::clean($child);

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WHOLE, true)) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            if (! array_key_exists($tag, self::ALLOW)) {
                self::unwrap($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attribute) {
                if (! in_array($attribute->name, self::ALLOW[$tag], true)) {
                    $child->removeAttribute($attribute->name);
                }
            }

            if ($tag === 'a' && preg_match(self::SAFE_SCHEME, $child->getAttribute('href')) !== 1) {
                $child->removeAttribute('href');
            }
        }
    }

    /** Keeps the author's text, drops the tag around it. */
    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}
