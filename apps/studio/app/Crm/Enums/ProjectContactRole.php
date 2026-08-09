<?php

namespace App\Crm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A wedding has many people attached with different roles: the couple books, a
 * parent may pay, a planner needs the timeline. Modelling one Project with many
 * typed contacts — rather than a Client with a date field — is what lets
 * portrait and wedding work share a schema (vision §3.1).
 */
#[TypeScript]
enum ProjectContactRole: string implements HasLabel
{
    case Primary = 'primary';
    case Partner = 'partner';
    case Payer = 'payer';
    case Planner = 'planner';
    case Vendor = 'vendor';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Hauptkontakt',
            self::Partner => 'Partner/in',
            self::Payer => 'Zahlt',
            self::Planner => 'Planung',
            self::Vendor => 'Dienstleister',
        };
    }
}
