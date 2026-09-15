<?php

namespace App\Payments\Support;

use App\Payments\Models\PaymentInstallment;
use App\Quotes\Support\VatLines;
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
        /**
         * #412: the same gross broken out by tax rate — § 14 Abs. 4 Nr. 7
         * UStG. One line for the ordinary quote, one per rate where its
         * lines carry their own, and always summing back to `gross`.
         *
         * @var list<array{rate: int, net: int, vat: int}>
         */
        public readonly array $rates,
    ) {}

    public static function forInstallment(PaymentInstallment $installment, StudioSettingsResolver $settings): self
    {
        $quote = $installment->acceptedQuote();

        $rate = $quote?->vat_mode === 'small_business'
            ? 0
            : ($quote?->vat_rate ?? $settings->effectiveVatRate());

        $note = $quote !== null
            ? $quote->vat_note
            : ($settings->vatMode() === 'small_business' ? $settings->vatNote() : null);

        $figures = self::fromGross((int) $installment->amount, (int) $rate, $note);

        // The Kleinunternehmer case short-circuits: there is one rate, it is
        // zero, and § 19 forbids showing it at all.
        $quoteLines = $rate === 0 ? [] : ($quote?->vatLines() ?? []);

        return count($quoteLines) < 2 ? $figures : $figures->splitOver($quoteLines);
    }

    public static function fromGross(int $gross, int $rate, ?string $vatNote = null): self
    {
        // Half-up on the net, then VAT as the remainder, so net + vat === gross
        // by construction for every input rather than for most of them.
        $net = $rate <= 0
            ? $gross
            : intdiv($gross * self::BASIS_POINTS + intdiv(self::BASIS_POINTS + $rate, 2), self::BASIS_POINTS + $rate);

        return new self($gross, $net, $gross - $net, $rate, $vatNote, [['rate' => $rate, 'net' => $net, 'vat' => $gross - $net]]);
    }

    /**
     * This gross shared out pro rata over the quote's rate lines. `rate`,
     * `net` and `vat` stay the single-figure view a one-rate template still
     * prints; `rates` is the breakdown, and both describe the same gross.
     *
     * @param  list<array{rate: int, net: int, vat: int}>  $quoteLines
     */
    private function splitOver(array $quoteLines): self
    {
        $rates = [];

        foreach (VatLines::grossShares($quoteLines, $this->gross) as $rate => $share) {
            $line = self::fromGross($share, (int) $rate);
            $rates[] = ['rate' => (int) $rate, 'net' => $line->net, 'vat' => $line->vat];
        }

        $net = array_sum(array_column($rates, 'net'));

        return new self($this->gross, $net, $this->gross - $net, $this->rate, $this->vatNote, $rates);
    }

    /** The rate as a printable percentage — 1900 basis points reads as "19 %". */
    public function ratePercentage(): string
    {
        $percent = $this->rate / 100;

        return rtrim(rtrim(number_format($percent, 2, ',', '.'), '0'), ',').' %';
    }
}
