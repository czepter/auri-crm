<?php

namespace App\Gallery\Support;

use App\Gallery\Models\GalleryPreset;
use Illuminate\Validation\Rule;

final class GalleryDesign
{
    public const OPTIONS = [
        'cover' => ['zentriert', 'links', 'rahmen', 'geteilt'],
        'type' => ['sans', 'serif', 'modern', 'zeitlos', 'kraeftig', 'dezent'],
        'colour' => ['hell', 'gold', 'rose', 'terracotta', 'sand', 'olive', 'agave', 'meer', 'dunkel'],
        'size' => ['klein', 'mittel', 'gross'],
        'spacing' => ['eng', 'weit'],
        'flow' => ['spalten', 'zeilen'],
    ];

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(string $prefix, string $presence = 'nullable'): array
    {
        return collect(self::OPTIONS)
            ->mapWithKeys(fn (array $values, string $key) => ["{$prefix}.{$key}" => [$presence, Rule::in($values)]])
            ->all();
    }

    /**
     * Was ein Betrachter rendert: fehlende oder unbekannte Schluessel fallen
     * auf die eingebauten Werte, damit nie eine halbe Gestaltung ankommt.
     *
     * @param  array<string, mixed>|null  $design
     * @return array<string, string>
     */
    public static function resolve(?array $design): array
    {
        $resolved = [];

        foreach (self::OPTIONS as $key => $values) {
            $value = $design[$key] ?? null;
            $resolved[$key] = in_array($value, $values, true) ? $value : GalleryPreset::DESIGN[$key];
        }

        return $resolved;
    }
}
