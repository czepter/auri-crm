<?php

namespace App\Documents\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * T-12's whole point: one template model, four documents. Contract, Quote
 * and Invoice are PDF-shell documents — DocumentRenderer wraps their resolved
 * body in the studio's shared letterhead/footer before it ever reaches
 * Gotenberg. Mail is not a PDF at all (App\Quotes\Notifications\QuoteSent's
 * shape) — DocumentRenderer leaves it unwrapped.
 *
 * T-15 added Invoice as a fourth case rather than a new axis: the one
 * document the studio had no wording for was the one it could not change.
 */
#[TypeScript]
enum DocumentTemplateType: string
{
    case Contract = 'contract';
    case Quote = 'quote';
    case Invoice = 'invoice';
    case Mail = 'mail';

    public function label(): string
    {
        return match ($this) {
            self::Contract => 'Vertrag',
            self::Quote => 'Angebot',
            self::Invoice => 'Rechnung',
            self::Mail => 'E-Mail',
        };
    }
}
