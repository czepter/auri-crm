<?php

namespace App\Documents\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * T-12's whole point: one template model, many documents. Contract, Quote,
 * Invoice and CreditNote are PDF-shell documents — DocumentRenderer wraps
 * their resolved body in the studio's shared letterhead/footer before it ever
 * reaches Gotenberg. Mail is not a PDF at all (App\Quotes\Notifications\
 * QuoteSent's shape) — DocumentRenderer leaves it unwrapped.
 *
 * T-15 added Invoice as a fourth case rather than a new axis: the one
 * document the studio had no wording for was the one it could not change.
 * #409 added CreditNote the same way — a Gutschrift is the invoice's wording
 * with the sign reversed, not a new kind of thing.
 */
#[TypeScript]
enum DocumentTemplateType: string
{
    case Contract = 'contract';
    case Quote = 'quote';
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
    case Mail = 'mail';

    /* Impressum, AGB, Datenschutz, Widerrufsbelehrung: Prosa ohne Briefkopf.
       Heißt „Seite", weil daraus keins der fünf Dokumente darüber wird,
       sondern eine Seite, die im Portal steht. */
    case Legal = 'legal';

    public function label(): string
    {
        return match ($this) {
            self::Contract => 'Vertrag',
            self::Quote => 'Angebot',
            self::Invoice => 'Rechnung',
            self::CreditNote => 'Gutschrift',
            self::Mail => 'E-Mail',
            self::Legal => 'Seite',
        };
    }
}
