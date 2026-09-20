<?php

namespace App\Documents\Support;

/**
 * Blocks → HTML, for the starters only.
 *
 * The create screen serialises in the browser (`resources/js/admin/documents/
 * blocks/serialize.ts`), because that is where the editor lives. `T-18` seeds
 * templates server-side with no browser in the loop, so the same document has
 * to become the same HTML here.
 *
 * **This is a deliberate mirror, and it is bounded on purpose.** It handles
 * exactly the block types StarterTemplateCatalog uses and throws on anything
 * else, so a starter reaching for a block this cannot render fails loudly in
 * the catalog's own test rather than seeding a template with a hole in it. The
 * general case stays in TypeScript, where the editor's tools define it — the
 * same arrangement `fillSamples()` documents for MergeFieldResolver::PATTERN.
 *
 * Merge fields are plain `{{ path }}` text in a catalog entry rather than the
 * editor's `data-mf` chips: the chip form exists so the editor can show a
 * sample value in place of a path, and a starter is written by hand in PHP
 * where there is nothing to show it to. Both forms serialise to the same
 * `{{ path }}`, which is all MergeFieldResolver ever sees.
 */
class StarterBlockSerializer
{
    /** Every type this can render — asserted against the catalog by StarterTemplateCatalogTest. */
    public const SUPPORTED = ['header', 'paragraph', 'list', 'delimiter', 'table', 'clause'];

    /** @param array{blocks: list<array{type: string, data: array<string, mixed>}>} $document */
    public static function toHtml(array $document): string
    {
        return collect($document['blocks'] ?? [])
            ->map(fn (array $block) => self::block($block))
            ->implode('');
    }

    /** @param array{type: string, data: array<string, mixed>} $block */
    private static function block(array $block): string
    {
        $data = $block['data'] ?? [];

        return match ($block['type']) {
            'header' => self::header($data),
            'paragraph' => '<p>'.self::text($data['text'] ?? '').'</p>',
            'list' => self::list($data),
            'delimiter' => '<hr class="doc-rule">',
            'table' => self::table($data),
            'clause' => '<section class="doc-clause"><h3>'.self::text($data['title'] ?? '').'</h3><p>'.self::text($data['text'] ?? '').'</p></section>',
            default => throw new \InvalidArgumentException(
                "StarterBlockSerializer cannot render a '{$block['type']}' block. Add it here and to serialize.ts, or keep it out of the catalog."
            ),
        };
    }

    /** @param array<string, mixed> $data */
    private static function header(array $data): string
    {
        $level = min(3, max(1, (int) ($data['level'] ?? 2)));

        return "<h{$level}>".self::text($data['text'] ?? '')."</h{$level}>";
    }

    /** @param array<string, mixed> $data */
    private static function list(array $data): string
    {
        $tag = ($data['style'] ?? 'unordered') === 'ordered' ? 'ol' : 'ul';
        $items = collect($data['items'] ?? [])
            ->map(fn ($item) => '<li>'.self::text(is_array($item) ? ($item['content'] ?? '') : $item).'</li>')
            ->implode('');

        return "<{$tag}>{$items}</{$tag}>";
    }

    /** @param array<string, mixed> $data */
    private static function table(array $data): string
    {
        $rows = $data['content'] ?? [];

        $cells = fn (array $row, string $tag) => collect($row)
            ->map(fn ($value) => "<{$tag}>".self::text($value)."</{$tag}>")
            ->implode('');

        if (($data['withHeadings'] ?? false) && $rows !== []) {
            [$head, $body] = [array_shift($rows), $rows];

            return '<table class="doc-table"><thead><tr>'.$cells($head, 'th').'</tr></thead><tbody>'
                .collect($body)->map(fn (array $row) => '<tr>'.$cells($row, 'td').'</tr>')->implode('')
                .'</tbody></table>';
        }

        return '<table class="doc-table"><tbody>'
            .collect($rows)->map(fn (array $row) => '<tr>'.$cells($row, 'td').'</tr>')->implode('')
            .'</tbody></table>';
    }

    /**
     * Catalog text is written by hand in this repository, never by a user, so
     * the inline markup it may carry (`<b>`, `<i>`) is exactly what the author
     * typed. Nothing is escaped here for the same reason serialize.ts does not
     * escape a block's own text: escaping it would print the tags.
     */
    private static function text(mixed $value): string
    {
        return (string) $value;
    }
}
