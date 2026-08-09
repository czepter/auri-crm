<?php

namespace App\Documents\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * T-12's whole point: one template model, three documents. Contract and
 * Quote are PDF-shell documents — DocumentRenderer wraps their resolved body
 * in the studio's shared letterhead/footer before it ever reaches Gotenberg.
 * Mail is not a PDF at all (App\Quotes\Notifications\QuoteSent's shape) —
 * DocumentRenderer leaves it unwrapped.
 */
#[TypeScript]
enum DocumentTemplateType: string
{
    case Contract = 'contract';
    case Quote = 'quote';
    case Mail = 'mail';

    public function label(): string
    {
        return match ($this) {
            self::Contract => 'Vertrag',
            self::Quote => 'Angebot',
            self::Mail => 'E-Mail',
        };
    }
}
