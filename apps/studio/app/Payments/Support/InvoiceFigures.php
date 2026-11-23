<?php

namespace App\Payments\Support;

use App\Models\StudioSettings;
use App\Payments\Models\Invoice;
use App\Payments\Models\InvoiceItem;
use App\Payments\Models\PaymentInstallment;
use App\Quotes\Enums\DiscountType;
use App\Quotes\Models\Quote;
use App\Quotes\Support\VatLines;

/**
 * The net / VAT / gross split one invoice prints, derived from the gross it
 * bills — the sum of its installments, or of its items.
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
 * and StudioSettings' vat_rate already share.
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

    /**
     * #480: a project invoice takes its rate from the accepted quote over the
     * sum of its installments; an invoice without a project splits each
     * item's gross at the item's own rate.
     */
    public static function forInvoice(Invoice $invoice): self
    {
        // Rates alone keep their plan's own split over the quote's rates (#412).
        return $invoice->items->whereNull('payment_installment_id')->isNotEmpty()
            ? self::forPositions($invoice->items, $invoice->discount_type, $invoice->discount_value)
            : self::forGross($invoice->amount(), self::acceptedQuote($invoice));
    }

    /**
     * #480: positions written with the quote's builder. Free lines take the
     * quote's own arithmetic — line and invoice discount, a net per rate,
     * VAT on top. A line picked from a payment plan is that installment's
     * gross split backwards, so the invoice asks for exactly what the plan
     * says is owed. The invoice discount reaches free lines only.
     *
     * @param  iterable<InvoiceItem>  $items
     */
    public static function forPositions(iterable $items, ?DiscountType $discountType, ?int $discountValue): self
    {
        $items = collect($items);
        $smallBusiness = StudioSettings::current()->vat_mode === 'small_business';
        $defaultRate = $smallBusiness ? 0 : StudioSettings::current()->effectiveVatRate();

        [$picked, $free] = $items->partition(fn (InvoiceItem $item) => $item->payment_installment_id !== null);

        $lines = [];

        if ($free->isNotEmpty()) {
            $quoteItems = $free->map(fn (InvoiceItem $item) => $item->asQuoteItem());
            $net = Quote::computeTotals($quoteItems, $discountType, $discountValue)['net'];
            $lines = $smallBusiness
                ? [['rate' => 0, 'net' => $net, 'vat' => 0]]
                : VatLines::fromItems($quoteItems, $defaultRate, $net);
        }

        foreach ($picked as $item) {
            $line = self::fromGross((int) $item->installment?->amount, $smallBusiness ? 0 : (int) ($item->vat_rate ?? $defaultRate));
            $lines[] = ['rate' => $line->rate, 'net' => $line->net, 'vat' => $line->vat];
        }

        $rates = collect($lines)
            ->groupBy('rate')
            ->map(fn ($group, $rate) => ['rate' => (int) $rate, 'net' => $group->sum('net'), 'vat' => $group->sum('vat')])
            ->sortByDesc('net')
            ->values()
            ->all();

        $net = array_sum(array_column($rates, 'net'));
        $vat = array_sum(array_column($rates, 'vat'));

        return new self($net + $vat, $net, $vat, $rates[0]['rate'] ?? $defaultRate, $smallBusiness ? StudioSettings::current()->vat_note : null, $rates);
    }

    /**
     * What a payment received on one rate was made of. A rate on an invoice
     * splits at that invoice's rates; one outside any invoice at the accepted
     * quote's rate, else the studio's. Callers that value many rates of the
     * same invoice hand in its figures once.
     */
    public static function forInstallment(PaymentInstallment $installment, ?self $invoiceFigures = null): self
    {
        $gross = (int) $installment->paid_amount;

        return $installment->invoice === null
            ? self::forGross($gross, $installment->acceptedQuote())
            : ($invoiceFigures ?? self::forInvoice($installment->invoice))->share($gross);
    }

    /** A part of an invoice — a Gutschrift, a deducted Anzahlung — at that invoice's rate. */
    public function share(int $gross): self
    {
        if (count($this->rates) < 2) {
            return self::fromGross($gross, $this->rate, $this->vatNote);
        }

        $rates = [];
        foreach (VatLines::grossShares($this->rates, $gross) as $rate => $part) {
            $line = self::fromGross($part, (int) $rate);
            $rates[] = ['rate' => (int) $rate, 'net' => $line->net, 'vat' => $line->vat];
        }
        $net = array_sum(array_column($rates, 'net'));

        return new self($gross, $net, $gross - $net, $this->rate, $this->vatNote, $rates);
    }

    private static function forGross(int $gross, ?Quote $quote): self
    {
        $rate = $quote?->vat_mode === 'small_business'
            ? 0
            : ($quote?->vat_rate ?? StudioSettings::current()->effectiveVatRate());

        $note = $quote !== null
            ? $quote->vat_note
            : (StudioSettings::current()->vat_mode === 'small_business' ? StudioSettings::current()->vat_note : null);

        $figures = self::fromGross($gross, (int) $rate, $note);

        // The Kleinunternehmer case short-circuits: there is one rate, it is
        // zero, and § 19 forbids showing it at all.
        $quoteLines = $rate === 0 ? [] : ($quote?->vatLines() ?? []);

        return count($quoteLines) < 2 ? $figures : $figures->splitOver($quoteLines);
    }

    /**
     * A revision chain (Q-06) leaves at most one row in `accepted`, so this
     * cannot silently pick between two.
     */
    private static function acceptedQuote(Invoice $invoice): ?Quote
    {
        return $invoice->case?->quotes()
            ->where('status', 'accepted')
            ->latest('accepted_at')
            ->first();
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
