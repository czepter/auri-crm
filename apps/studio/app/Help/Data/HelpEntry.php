<?php

namespace App\Help\Data;

/**
 * Ein Artikel ohne seinen Text. Was der Index hält, damit die geteilte Prop
 * auf jedem Request beantwortbar ist, ohne eine Datei zu lesen.
 */
final readonly class HelpEntry
{
    /** @param  array<string>  $screens */
    public function __construct(
        public string $slug,
        public string $titel,
        public string $beschreibung,
        public string $art,
        public ?int $reihenfolge,
        public array $screens,
        public ?string $faehigkeit,
        public string $suchtext,
    ) {}
}
