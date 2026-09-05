<?php

namespace App\Payments\Support;

use App\Payments\Models\PaymentInstallment;
use App\Quotes\Models\Quote;
use App\Settings\Support\StudioSettingsResolver;

/**
 * The net / VAT / gross split one invoice prints, derived from the one number
 * an installment actually holds.
 *
 * An installment's `amount` is the payable — what the client transfers, what
 * the portal's Bezahlen button charges. So the split runs *backwards* out of
 * the gross rather than forwards from a net that is nowhere stored, and the
 * VAT is whatever is left over. Deriving it the other way round would let
 * net + VAT disagree with the amount the client is being asked for, which on
 * an invoice is not a rounding nicety but a wrong document.
 *
 * The rate and mode come from the project's accepted quote when there is one,
 * because Q-04 froze them there at send and that snapshot — not today's
 * settings — is what the client agreed to. A plan created without a quote
 * behind it falls back to the studio's current setting, which is the only
 * other answer available.
 *
 * Basis points throughout (1900 = 19%), the convention DiscountCalculator
 * and StudioSettingsResolver::vatRate() already share.
 */
final class InvoiceFigures
{
    private const BASIS_POINTS = 10_000;

    private function __construct(
        public readonly int $gross,
        public readonly int $net,
        public readonly int $vat,
        public readonly int $rate,
        public readonly ?string $vatNote,
    ) {}

    public static function forInstallment(PaymentInstallment $installment, StudioSettingsResolver $settings): self
    {
        $quote = self::acceptedQuote($installment);

        $rate = $quote?->vat_mode === 'small_business'
            ? 0
            : ($quote?->vat_rate ?? $settings->effectiveVatRate());

        $note = $quote !== null
            ? $quote->vat_note
            : ($settings->vatMode() === 'small_business' ? $settings->vatNote() : null);

        return self::fromGross((int) $installment->amount, (int) $rate, $note);
    }

    public static function fromGross(int $gross, int $rate, ?string $vatNote = null): self
    {
        // Half-up on the net, then VAT as the remainder, so net + vat === gross
        // by construction for every input rather than for most of them.
        $net = $rate <= 0
            ? $gross
            : intdiv($gross * self::BASIS_POINTS + intdiv(self::BASIS_POINTS + $rate, 2), self::BASIS_POINTS + $rate);

        return new self($gross, $net, $gross - $net, $rate, $vatNote);
    }

    /** The rate as a printable percentage — 1900 basis points reads as "19 %". */
    public function ratePercentage(): string
    {
        $percent = $this->rate / 100;

        return rtrim(rtrim(number_format($percent, 2, ',', '.'), '0'), ',').' %';
    }

    /**
     * The one accepted quote behind this installment's project, if any. A
     * revision chain (Q-06) leaves at most one row in `accepted`, so this
     * cannot silently pick between two.
     */
    private static function acceptedQuote(PaymentInstallment $installment): ?Quote
    {
        return $installment->plan?->job?->project?->quotes()
            ->where('status', 'accepted')
            ->latest('accepted_at')
            ->first();
    }
}
