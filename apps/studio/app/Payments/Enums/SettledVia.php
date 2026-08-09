<?php

namespace App\Payments\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How an installment WAS settled, recorded once by
 * App\Payments\Actions\MarkInstallmentPaid — never how it is going to be.
 * Nothing upstream ever asks "how will this be paid" and stores an answer in
 * advance; the column this backs (payment_installments.settled_via) stays
 * null until the moment a payment actually lands, whichever of these three
 * ways it happened to arrive.
 */
#[TypeScript]
enum SettledVia: string
{
    case Stripe = 'stripe';
    case Transfer = 'transfer';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Stripe => 'Stripe',
            self::Transfer => 'Überweisung',
            self::Manual => 'Manuell',
        };
    }
}
