<?php

namespace App\Documents\Support;

/**
 * Resolves `{{ project.name }}`-style merge fields against a plain nested
 * array — never against a model, and never inside a controller. The same
 * resolver for every App\Documents\Enums\DocumentTemplateType: a quote, an
 * invoice and a mail all read `{{ project.name }}` the same way, because they
 * all run through this one class against App\Documents\Support\
 * DocumentMergeDataBuilder's one array. See docs/documents.md for the syntax.
 */
class MergeFieldResolver
{
    private const PATTERN = '/\{\{\s*([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*)\s*\}\}/';

    /**
     * @param  array<string, mixed>  $data  Dot-addressable, e.g. ['project' => ['name' => 'Meier & Kowalski']].
     * @param  bool  $keepUnknown  E-15: leave an unresolvable field standing as written rather than
     *                             blanking it. A document silently drops what it cannot fill; an
     *                             automation rule is edited in a box with a preview beside it, and a
     *                             visible gap is the thing that gets fixed.
     */
    public static function resolve(string $body, array $data, bool $keepUnknown = false): string
    {
        return preg_replace_callback(
            self::PATTERN,
            fn (array $match) => (string) (data_get($data, $match[1]) ?? ($keepUnknown ? $match[0] : '')),
            $body,
        );
    }
}
