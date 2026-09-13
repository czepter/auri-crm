<?php

namespace App\Catalog\Enums;

use App\Crm\Enums\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Wie der Preis eines Pakets im Portal steht. Drei Fälle, weil es drei gibt:
 * ein fester Preis, ein Einstiegspreis für alles mit Menge (Album, Abzüge),
 * und keiner — eine Leistung, die ohne Gespräch nicht kalkulierbar ist.
 */
#[TypeScript]
enum PortalPriceMode: string implements HasLabel
{
    case Exact = 'exact';
    case From = 'from';
    case OnRequest = 'on_request';

    public function label(): string
    {
        return match ($this) {
            self::Exact => 'Fester Preis',
            self::From => 'Ab-Preis',
            self::OnRequest => 'Auf Anfrage',
        };
    }
}
