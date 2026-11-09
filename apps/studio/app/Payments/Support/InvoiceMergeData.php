<?php

namespace App\Payments\Support;

use App\Crm\Models\ClientCase;
use App\Crm\Support\CaseMergeData;
use App\Documents\Contracts\MergeDataProvider;
use App\Documents\Support\DocumentMergeData;
use App\Models\StudioSettings;
use App\Payments\Data\PaymentPlanData;
use App\Payments\Models\CreditNote;
use App\Payments\Models\Invoice;
use App\Payments\Models\InvoiceItem;
use App\Payments\Models\PaymentInstallment;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Quotes\Support\VatLines;
use Illuminate\Database\Eloquent\Model;

/**
 * The invoice and Gutschrift blocks: empty on every other document, filled
 * by forInvoice(). An invoice and a Gutschrift are the two things a merge
 * array cannot derive from the case — an invoice is about some of the
 * amounts due and a Gutschrift about one refund of it, and only the caller
 * rendering one knows which.
 */
class InvoiceMergeData implements MergeDataProvider
{
    private const INVOICE_KEYS = ['number', 'date', 'servicePeriod', 'description', 'net', 'vatRate', 'vat', 'gross', 'vatNote', 'dueOn', 'paymentTerm'];

    /** @return array<string, array<string, mixed>> */
    public function blocks(?Model $case): array
    {
        return [
            'invoice' => array_fill_keys(self::INVOICE_KEYS, '')
                + ['vatLines' => [], 'lines' => [], 'deposits' => [], 'totalNet' => '', 'totalVat' => '', 'totalGross' => ''],
            'creditNote' => $this->creditNote(null, null),
        ];
    }

    /**
     * #480: the invoice names its own recipient — the primary contact at
     * issue, and still the addressee after the project changed hands. An
     * invoice without a project (a print order years after the event) gets
     * the same shape, with what only a project knows left empty.
     *
     * DocumentMergeData is resolved here rather than injected: it gathers
     * this class as one of its providers.
     *
     * @return array<string, mixed>
     */
    public function forInvoice(Invoice $invoice, ?CreditNote $creditNote = null): array
    {
        $case = $invoice->case;

        return [
            ...app(DocumentMergeData::class)->forCase($case),
            'contact' => CaseMergeData::contact($invoice->contact ?? $case?->primaryContact()),
            'invoice' => $this->invoice($invoice, $case),
            'creditNote' => $this->creditNote($creditNote, $case),
        ];
    }

    /**
     * The §14 UStG particulars.
     *
     * The Leistungszeitraum is the primary shoot's date rather than the
     * invoice's own — the supply happened on the shoot day, and an invoice
     * issued three weeks later that claims otherwise is wrong in the one
     * field a Betriebsprüfung reads first. A project with no primary shoot
     * yet leaves it empty rather than guessing. An invoice without a project
     * bills a supply made when it is issued, and says so.
     *
     * An Anzahlung whose transfer arrived before the paperwork prints the
     * day it arrived instead: § 14 Abs. 4 Nr. 6 UStG asks for the Zeitpunkt
     * der Vereinnahmung in an Abs. 5 case, and the shoot is still ahead.
     *
     * @return array<string, string|list<array<string, string>>>
     */
    private function invoice(Invoice $invoice, ?ClientCase $project): array
    {
        $figures = InvoiceFigures::forInvoice($invoice);
        $installments = $invoice->installments;
        $dueOn = $installments->max('due_on')?->format('d.m.Y') ?? '';
        // paid_on is only ever written alongside the paid status, by
        // MarkInstallmentPaid — its presence is the settlement.
        $receivedOn = $installments->every(fn (PaymentInstallment $row) => $row->paid_on?->lt($invoice->issued_at) ?? false)
            ? $installments->max('paid_on')
            : null;
        $servicePeriod = $project === null
            ? $invoice->issued_at->format('d.m.Y')
            : $project->shoots()->where('is_primary', true)->first()?->date?->format('d.m.Y') ?? '';

        return [
            'number' => $invoice->number,
            'date' => $invoice->issued_at->format('d.m.Y'),
            'servicePeriod' => $receivedOn?->format('d.m.Y') ?? $servicePeriod,
            'description' => $this->description($invoice),
            'net' => DocumentMergeData::money($figures->net),
            'vatRate' => $figures->ratePercentage(),
            'vat' => DocumentMergeData::money($figures->vat),
            'gross' => DocumentMergeData::money($figures->gross),
            'vatNote' => $figures->vatNote ?? '',
            // #412: structured rather than a `{{ }}` path — a template body
            // cannot loop, so this is read by the layouts
            // (App\Payments\Support\InvoiceDocument) and by nothing else.
            'vatLines' => array_map(fn (array $line) => [
                'rate' => VatLines::percent($line['rate']).' %',
                'net' => DocumentMergeData::money($line['net']),
                'vat' => DocumentMergeData::money($line['vat']),
            ], $figures->rates),
            'dueOn' => $dueOn,
            'paymentTerm' => $dueOn === '' ? '' : "Zahlbar bis {$dueOn} ohne Abzug.",
            // #411: rates alone print as the one line of what they bill, or as
            // the quote's whole Leistung on an Endrechnung; positions written
            // with the builder print as themselves.
            ...($invoice->items->whereNull('payment_installment_id')->isEmpty()
                ? $this->finalInvoice($invoice)
                : $this->positionLines($invoice, $figures)),
        ];
    }

    /** "Hochzeit Kranz — Anzahlung, 2. Rate", or the positions' titles. */
    private function description(Invoice $invoice): string
    {
        if ($invoice->items->whereNull('payment_installment_id')->isNotEmpty()) {
            return $invoice->items->pluck('title')->implode(', ');
        }

        $rates = $invoice->installments
            ->map(fn (PaymentInstallment $row) => PaymentPlanData::labelForInstallment($row))
            ->implode(', ');

        return collect([$invoice->case?->name, $rates])->filter()->implode(' — ');
    }

    /**
     * #480: the positions as the quote's builder wrote them. A position picked
     * from a payment plan prints the installment's net — its gross split at
     * the line's rate — so the lines add up to what the plan asks for.
     *
     * @return array{lines: list<array<string, string>>, deposits: list<array<string, string>>, totalNet: string, totalVat: string, totalGross: string}
     */
    private function positionLines(Invoice $invoice, InvoiceFigures $figures): array
    {
        $smallBusiness = StudioSettings::current()->vat_mode === 'small_business';
        $defaultRate = StudioSettings::current()->effectiveVatRate();
        $free = $invoice->items->whereNull('payment_installment_id')->map(fn (InvoiceItem $item) => $item->asQuoteItem());
        $discount = Quote::computeTotals($free, $invoice->discount_type, $invoice->discount_value)['discount_amount'];

        $lines = $invoice->items->map(function (InvoiceItem $item) use ($smallBusiness, $defaultRate) {
            $rate = (int) ($item->vat_rate ?? $defaultRate);

            if ($item->payment_installment_id !== null) {
                $net = InvoiceFigures::fromGross((int) $item->installment?->amount, $smallBusiness ? 0 : $rate)->net;

                return ['title' => $item->title, 'quantity' => '1', 'unitPrice' => DocumentMergeData::money($net), 'discount' => '', 'total' => DocumentMergeData::money($net), 'vatRate' => $smallBusiness ? '' : VatLines::percent($rate).' %'];
            }

            $line = $item->asQuoteItem();

            return [
                'title' => $item->title,
                'quantity' => (string) $item->quantity,
                'unitPrice' => DocumentMergeData::money((int) $item->unit_price),
                'discount' => $line->discount_amount > 0 ? '−'.DocumentMergeData::money((int) $line->discount_amount) : '',
                'total' => DocumentMergeData::money($line->discountedLineTotal()),
                'vatRate' => $smallBusiness ? '' : VatLines::percent($rate).' %',
            ];
        })->values()->all();

        // § 14 Abs. 4 Nr. 7 UStG: an agreed Minderung is stated, never folded into the prices.
        if ($discount > 0) {
            $lines[] = ['title' => 'Rabatt', 'quantity' => '', 'unitPrice' => '', 'discount' => '', 'total' => '−'.DocumentMergeData::money($discount), 'vatRate' => ''];
        }

        return [
            'lines' => $lines,
            'deposits' => [],
            'totalNet' => DocumentMergeData::money($figures->net),
            'totalVat' => DocumentMergeData::money($figures->vat),
            'totalGross' => DocumentMergeData::money($figures->gross),
        ];
    }

    /**
     * The Endrechnung's three additions: the whole Leistung line by line, its
     * own totals, and every Teilentgelt already invoiced.
     *
     * The Leistung comes from the accepted quote rather than from the plan's
     * total — the quote is what the client agreed to, and a plan can hold a
     * Nachtrag or a voided rate that never described a supply.
     *
     * @return array{lines: list<array<string, string>>, deposits: list<array<string, string>>, totalNet: string, totalVat: string, totalGross: string}
     */
    private function finalInvoice(Invoice $invoice): array
    {
        $final = $invoice->installments->first(fn (PaymentInstallment $row) => $row->isFinal());
        $quote = $final?->acceptedQuote()?->asSeenByClient();

        if ($quote === null) {
            return ['lines' => [], 'deposits' => [], 'totalNet' => '', 'totalVat' => '', 'totalGross' => ''];
        }

        $vat = $quote->vat_mode === 'standard' ? (int) $quote->vat_amount : 0;

        return [
            'lines' => $quote->hasSlider() ? $this->sliderLine($quote) : $this->quoteLines($quote),
            'deposits' => $this->deposits($invoice),
            'totalNet' => DocumentMergeData::money((int) $quote->net),
            'totalVat' => DocumentMergeData::money($vat),
            'totalGross' => DocumentMergeData::money((int) $quote->net + $vat),
        ];
    }

    /**
     * The quote's own positions, plus the quote-level discount as a line of
     * its own — § 14 Abs. 4 Nr. 7 counts any Minderung agreed in advance as
     * part of the Entgelt and wants it stated.
     *
     * @return list<array<string, string>>
     */
    private function quoteLines(Quote $quote): array
    {
        $lines = $quote->items
            ->map(fn (QuoteItem $item) => [
                'title' => $item->label(),
                'quantity' => (string) $item->quantity,
                'unitPrice' => $item->isIncluded() ? '' : DocumentMergeData::money((int) $item->unit_price),
                'discount' => $item->discount_amount > 0 ? '−'.DocumentMergeData::money((int) $item->discount_amount) : '',
                'total' => $item->isIncluded() ? 'enthalten' : DocumentMergeData::money($item->discountedLineTotal()),
                'vatRate' => VatLines::percent((int) ($item->vat_rate ?? $quote->vat_rate)).' %',
            ])
            ->values()
            ->all();

        if ($quote->discount_amount > 0) {
            $lines[] = [
                'title' => 'Rabatt',
                'quantity' => '',
                'unitPrice' => '',
                'discount' => '',
                'total' => '−'.DocumentMergeData::money((int) $quote->discount_amount),
                'vatRate' => '',
            ];
        }

        return $lines;
    }

    /**
     * A slider quote has no positions: the supply is hours at a rate, and
     * that is the one line — the same figure RenderQuotePdf prints for it.
     *
     * @return list<array<string, string>>
     */
    private function sliderLine(Quote $quote): array
    {
        $hours = (int) ($quote->hours ?? $quote->min_hours ?? 0);

        $lines = [[
            'title' => $quote->time_title ?? $hours.' Stunden',
            'quantity' => (string) $hours,
            'unitPrice' => DocumentMergeData::money((int) $quote->hourly_rate),
            'discount' => '',
            'total' => DocumentMergeData::money($hours * (int) $quote->hourly_rate),
            'vatRate' => VatLines::percent((int) $quote->vat_rate).' %',
        ]];

        if ($quote->discount_amount > 0 && ! $quote->discount_hidden) {
            $lines[] = ['title' => 'Rabatt', 'quantity' => '', 'unitPrice' => '', 'discount' => '', 'total' => '−'.DocumentMergeData::money((int) $quote->discount_amount), 'vatRate' => ''];
        }

        return $lines;
    }

    /**
     * Every earlier invoice on the plan that still stands, as the deduction it
     * is — one line per invoice, however many rates it carried. A credited
     * one is deducted net of its Gutschriften: that much of the Teilentgelt
     * was handed back and was never part of the Leistung.
     *
     * @return list<array<string, string|int>>
     */
    private function deposits(Invoice $invoice): array
    {
        return Invoice::query()
            // An unsaved preview comes after every invoice that exists.
            ->when($invoice->exists, fn ($query) => $query->where('id', '<', $invoice->id))
            ->whereNull('cancelled_at')
            ->whereHas('installments', fn ($query) => $query->where('payment_plan_id', $invoice->installments->first()?->payment_plan_id))
            ->with(['installments', 'creditNotes', 'case'])
            ->orderBy('id')
            ->get()
            ->map(function (Invoice $earlier) {
                $figures = InvoiceFigures::forInvoice($earlier)->share($earlier->amount() - $earlier->creditedAmount());

                return [
                    'title' => $earlier->installments->map(fn ($row) => PaymentPlanData::labelForInstallment($row))->implode(', '),
                    'number' => $earlier->number,
                    'date' => $earlier->issued_at->format('d.m.Y'),
                    'net' => DocumentMergeData::money($figures->net),
                    'vat' => DocumentMergeData::money($figures->vat),
                    'gross' => DocumentMergeData::money($figures->gross),
                    'vatAmount' => $figures->vat,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * #409: the Gutschrift's own particulars, or empty strings on every
     * document that is not one.
     *
     * The split runs off the credit note's amount rather than the
     * installment's, so a partial Gutschrift states the VAT actually being
     * given back; the rate itself still comes from the installment, because
     * a refund carries the tax rate of the supply it reverses, not today's.
     *
     * @return array<string, string>
     */
    private function creditNote(?CreditNote $creditNote, ?ClientCase $project): array
    {
        $keys = ['number', 'date', 'servicePeriod', 'description', 'reason', 'net', 'vatRate', 'vat', 'gross', 'vatNote', 'invoiceNumber', 'invoiceDate'];

        if ($creditNote === null) {
            return array_fill_keys($keys, '');
        }

        $invoice = $creditNote->invoice;
        $figures = InvoiceFigures::forInvoice($invoice)->share((int) $creditNote->amount);

        return [
            'number' => $creditNote->number,
            'date' => $creditNote->issued_at->format('d.m.Y'),
            'servicePeriod' => $project?->shoots()->where('is_primary', true)->first()?->date?->format('d.m.Y')
                ?? $invoice->issued_at->format('d.m.Y'),
            'description' => $this->description($invoice),
            'reason' => $creditNote->reason,
            'net' => DocumentMergeData::money($figures->net),
            'vatRate' => $figures->ratePercentage(),
            'vat' => DocumentMergeData::money($figures->vat),
            'gross' => DocumentMergeData::money($figures->gross),
            'vatNote' => $figures->vatNote ?? '',
            'invoiceNumber' => $invoice->number,
            'invoiceDate' => $invoice->issued_at->format('d.m.Y'),
        ];
    }
}
