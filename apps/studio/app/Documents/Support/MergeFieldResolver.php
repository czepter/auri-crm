<?php

namespace App\Documents\Support;

/**
 * Resolves `{{ project.name }}`-style merge fields against a plain nested
 * array — never against a model, and never inside a controller. The same
 * resolver for every App\Documents\Enums\DocumentTemplateType: a quote, an
 * invoice and a mail all read `{{ project.name }}` the same way, because they
 * all run through this one class against App\Documents\Support\
 * DocumentMergeData's one array. See docs/documents.md for the syntax.
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
     * @param  bool  $escape  #480: for a body that is markup, so a contact named `<b>` stays text.
     */
    public static function resolve(string $body, array $data, bool $keepUnknown = false, bool $escape = false): string
    {
        return preg_replace_callback(
            self::PATTERN,
            fn (array $match) => ($escape ? e(...) : strval(...))((string) (data_get($data, $match[1]) ?? ($keepUnknown ? $match[0] : ''))),
            $body,
        );
    }

    /**
     * The reverse of resolve(): puts a field back wherever its value stands in
     * $text. Longest values first, so a number is not eaten by a shorter one
     * inside it, and nothing under four characters, which would turn every
     * "ab" into a field.
     *
     * @param  array<string, mixed>  $data
     */
    public static function unresolve(string $text, array $data): string
    {
        $values = [];

        foreach (MergeFieldVocabulary::paths() as $path) {
            $value = data_get($data, $path);

            if (is_string($value) && mb_strlen($value) >= 4) {
                $values[$path] = $value;
            }
        }

        uasort($values, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($values as $path => $value) {
            $text = str_replace($value, "{{ {$path} }}", $text);
        }

        return $text;
    }
}
