<?php

namespace App\Contracts\Support;

/**
 * Resolves `{{ project.name }}`-style merge fields against a plain nested
 * array — never against a model, and never inside a controller. See
 * docs/contracts.md for the syntax and RenderContractJob for why the data
 * this resolves against is always assembled fresh, at the moment a render
 * runs, from whatever the project/job/contact look like right then.
 */
class MergeFieldResolver
{
    private const PATTERN = '/\{\{\s*([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*)\s*\}\}/';

    /**
     * @param  array<string, mixed>  $data  Dot-addressable, e.g. ['project' => ['name' => 'Meier & Kowalski']].
     */
    public static function resolve(string $body, array $data): string
    {
        return preg_replace_callback(
            self::PATTERN,
            fn (array $match) => (string) (data_get($data, $match[1]) ?? ''),
            $body,
        );
    }
}
