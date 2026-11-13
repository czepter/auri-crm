<?php

namespace App\Help\Support;

use Symfony\Component\Yaml\Yaml;

final class HelpFrontmatter
{
    /**
     * @return array{0: array<string, mixed>, 1: string} Kopf und Rumpf.
     */
    public static function parse(string $raw): array
    {
        // Ohne Kopf ist die ganze Datei Rumpf. Der Wächter-Test schlägt dann
        // an der fehlenden `art` an und benennt die Datei.
        if (! str_starts_with($raw, "---\n")) {
            return [[], $raw];
        }

        [, $kopf, $rumpf] = explode("---\n", $raw, 3);

        return [Yaml::parse($kopf) ?? [], $rumpf];
    }
}
