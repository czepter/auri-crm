<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The eight dots a stage may wear. A closed set rather than a free hex field:
 * every value is oklch(48% .07 h), so no stage's dot can outweigh its
 * neighbours in a row of them, and none of them can reach --accent, which in
 * this system means "press me" and nothing else.
 */
#[TypeScript]
enum StageColor: string implements HasLabel
{
    case Grey = 'grey';
    case Red = 'red';
    case Amber = 'amber';
    case Olive = 'olive';
    case Green = 'green';
    case Teal = 'teal';
    case Blue = 'blue';
    case Violet = 'violet';

    public function label(): string
    {
        return match ($this) {
            self::Grey => 'Grau',
            self::Red => 'Rot',
            self::Amber => 'Bernstein',
            self::Olive => 'Oliv',
            self::Green => 'Grün',
            self::Teal => 'Petrol',
            self::Blue => 'Blau',
            self::Violet => 'Violett',
        };
    }

    public function css(): string
    {
        return match ($this) {
            self::Grey => '#6E6E73',
            self::Red => 'oklch(48% .07 25)',
            self::Amber => 'oklch(48% .07 70)',
            self::Olive => 'oklch(48% .07 120)',
            self::Green => 'oklch(48% .07 150)',
            self::Teal => 'oklch(48% .07 195)',
            self::Blue => 'oklch(48% .07 245)',
            self::Violet => 'oklch(48% .07 300)',
        };
    }
}
