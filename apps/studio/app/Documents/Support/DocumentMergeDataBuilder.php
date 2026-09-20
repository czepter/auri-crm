<?php

namespace App\Documents\Support;

use App\Crm\Models\Project;
use App\Payments\Data\PaymentPlanData;
use App\Payments\Models\CreditNote;
use App\Payments\Models\Invoice;
use App\Payments\Models\InvoiceItem;
use App\Payments\Models\PaymentInstallment;
use App\Payments\Support\InvoiceFigures;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Quotes\Support\VatLines;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Support\Number;

/**
 * The one place a merge-field array is assembled from real data — T-12's
 * "resolved from one place" acceptance criterion. App\Contracts\Jobs\
 * RenderContractJob calls this rather than building its own array, and
 * whatever eventually renders a quote or a transactional mail calls it too:
 * one vocabulary, one builder, so `{{ project.name }}` can never mean two
 * different things depending which document reads it.
 *
 * Called at render time, never live from a controller — see RenderContractJob's
 * own doc comment for why: a document that read project data live would
 * silently change after a client already saw it.
 */
class DocumentMergeDataBuilder
{
    /**
     * $invoice and $creditNote are the two arguments not derivable from the
     * project: an invoice is about some of the amounts due and a Gutschrift
     * about one refund of it, not about the job as a whole, and only the
     * caller rendering one knows which. Every other document type passes null and
     * those blocks resolve to empty strings — present in the array rather
     * than absent, so a merge field used on the wrong document type renders
     * as nothing instead of leaving a raw placeholder in a PDF.
     *
     * @return array<string, mixed>
     */
    public function fromProject(
        Project $project,
        StudioSettingsResolver $settings,
        ?Invoice $invoice = null,
        ?CreditNote $creditNote = null,
    ): array {
        // #480: the invoice names its own recipient — the primary contact at
        // issue, and still the addressee after the project changed hands.
        $contact = $invoice?->contact ?? $project->primaryContact();

        return [
            'project' => [
                'name' => $project->name,
                'type' => $project->type->name,
            ],
            'contact' => [
                'name' => $contact?->name ?? '',
                'email' => $contact?->email ?? '',
                'street' => $contact?->street ?? '',
                'postcode' => $contact?->postcode ?? '',
                'city' => $contact?->city ?? '',
            ],
            'invoice' => $this->invoice($invoice, $project, $settings),
            'creditNote' => $this->creditNote($creditNote, $project, $settings),
            'job' => [
                'bookedAt' => $project->job?->booked_at?->format('d.m.Y') ?? '',
            ],
            // The occasion this document is about. Same primary shoot the
            // invoice's Leistungszeitraum reads, named plainly here because a
            // quote and a contract both talk about the day rather than about
            // a service period.
            'shoot' => [
                'date' => $project->primaryShoot?->date?->format('d.m.Y') ?? '',
            ],
            // What a quote says about itself. Empty on a project with no sent
            // quote — a contract template referencing them is asking about a
            // document that does not exist, which is exactly the case an
            // empty string is right for.
            'quote' => $this->quote($project, $settings),
            // C-17 (#253): the gallery this project delivered, with the two
            // secrets in clear. Empty where none is published — a template
            // that names them on a project without a gallery is asking about
            // something that does not exist.
            'gallery' => $this->gallery($project),
            'studio' => $this->studio($settings),
            'settings' => [
                'widerrufText' => $settings->widerrufIncluded() ? ($settings->widerrufText() ?? '') : '',
            ],
        ];
    }

    /**
     * #480: an invoice without a project — a print order years after the
     * event, to a guest who was never a client. The same array shape
     * fromProject() returns, so every layout and template reads it
     * unchanged; what only a project knows stays empty.
     *
     * @return array<string, mixed>
     */
    public function fromInvoice(Invoice $invoice, StudioSettingsResolver $settings, ?CreditNote $creditNote = null): array
    {
        if ($invoice->project !== null) {
            return $this->fromProject($invoice->project, $settings, $invoice, $creditNote);
        }

        $contact = $invoice->contact;

        return [
            'project' => ['name' => '', 'type' => ''],
            'contact' => [
                'name' => $contact?->name ?? '',
                'email' => $contact?->email ?? '',
                'street' => $contact?->street ?? '',
                'postcode' => $contact?->postcode ?? '',
                'city' => $contact?->city ?? '',
            ],
            'invoice' => $this->invoice($invoice, null, $settings),
            'creditNote' => $this->creditNote($creditNote, null, $settings),
            'job' => ['bookedAt' => ''],
            'shoot' => ['date' => ''],
            'quote' => ['validUntil' => '', 'vatNote' => ''],
            'gallery' => array_fill_keys(['title', 'link', 'password', 'downloadPin', 'expiresOn'], ''),
            'studio' => $this->studio($settings),
            'settings' => [
                'widerrufText' => $settings->widerrufIncluded() ? ($settings->widerrufText() ?? '') : '',
            ],
        ];
    }

    /**
     * A legal text belongs to the studio, not to a job: an Impressum or a
     * Widerrufsbelehrung names the same address whoever reads it. Everything
     * a project would fill stays out, so `{{ contact.name }}` on such a
     * template resolves to nothing rather than to the last reader.
     *
     * @return array<string, mixed>
     */
    public function fromStudio(StudioSettingsResolver $settings): array
    {
        return [
            'studio' => $this->studio($settings),
            'settings' => [
                'widerrufText' => $settings->widerrufIncluded() ? ($settings->widerrufText() ?? '') : '',
            ],
        ];
    }

    /** @return array<string, string> */
    private function studio(StudioSettingsResolver $settings): array
    {
        $studio = $settings->attributes();

        return [
            'name' => $studio['legal_name'] ?: ($studio['display_name'] ?? ''),
            'legalForm' => $studio['legal_form'] ?? '',
            'street' => $studio['street'] ?? '',
            'postcode' => $studio['postcode'] ?? '',
            'city' => $studio['city'] ?? '',
            'country' => $studio['country'] ?? '',
            'taxNumber' => $studio['tax_number'] ?? '',
            'vatId' => $studio['vat_id'] ?? '',
            'registerNumber' => $studio['register_number'] ?? '',
            'registerCourt' => $studio['register_court'] ?? '',
            'representative' => $studio['representative'] ?? '',
            'website' => $studio['website'] ?? '',
            'bankHolder' => $studio['bank_account_holder'] ?? '',
            'bankIban' => $studio['bank_iban'] ?? '',
            'bankBic' => $studio['bank_bic'] ?? '',
            // The stored file path, not a URL — no template embeds it as
            // an <img> today; see docs/documents.md.
            'logoPath' => $settings->logoPath() ?? '',
        ];
    }

    /**
     * The newest gallery of this project, or empty strings.
     *
     * The link is apps/gallery's own address (Gallery::publicUrl()) — the
     * guest viewer, not this app's own host, which no guest can open.
     *
     * @return array<string, string>
     */
    private function gallery(Project $project): array
    {
        $gallery = $project->galleries()->latest('id')->first();

        return [
            'title' => $gallery?->title ?? '',
            'link' => $gallery?->publicUrl() ?? '',
            'password' => $gallery?->view_password ?? '',
            'downloadPin' => $gallery?->download_pin ?? '',
            'expiresOn' => $gallery?->expires_at?->format('d.m.Y') ?? '',
        ];
    }

    /**
     * The §14 UStG particulars, or empty strings for every document that is
     * not an invoice.
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
    private function invoice(?Invoice $invoice, ?Project $project, StudioSettingsResolver $settings): array
    {
        if ($invoice === null) {
            return array_fill_keys(
                ['number', 'date', 'servicePeriod', 'description', 'net', 'vatRate', 'vat', 'gross', 'vatNote', 'dueOn', 'paymentTerm'],
                '',
            ) + ['vatLines' => [], 'lines' => [], 'deposits' => [], 'totalNet' => '', 'totalVat' => '', 'totalGross' => ''];
        }

        $figures = InvoiceFigures::forInvoice($invoice, $settings);
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
            'net' => $this->money($figures->net),
            'vatRate' => $figures->ratePercentage(),
            'vat' => $this->money($figures->vat),
            'gross' => $this->money($figures->gross),
            'vatNote' => $figures->vatNote ?? '',
            // #412: structured rather than a `{{ }}` path — a template body
            // cannot loop, so this is read by the layouts
            // (App\Payments\Support\InvoiceDocument) and by nothing else.
            'vatLines' => array_map(fn (array $line) => [
                'rate' => VatLines::percent($line['rate']).' %',
                'net' => $this->money($line['net']),
                'vat' => $this->money($line['vat']),
            ], $figures->rates),
            'dueOn' => $dueOn,
            'paymentTerm' => $dueOn === '' ? '' : "Zahlbar bis {$dueOn} ohne Abzug.",
            // #411: rates alone print as the one line of what they bill, or as
            // the quote's whole Leistung on an Endrechnung; positions written
            // with the builder print as themselves.
            ...($invoice->items->whereNull('payment_installment_id')->isEmpty()
                ? $this->finalInvoice($invoice, $settings)
                : $this->positionLines($invoice, $figures, $settings)),
        ];
    }

    /** "Hochzeit Kranz — Anzahlung, 2. Rate", or the positions' titles. */
    private function description(Invoice $invoice): string
    {
        if ($invoice->items->whereNull('payment_installment_id')->isNotEmpty()) {
            return $invoice->items->pluck('title')->implode(', ');
        }

        return $invoice->project->name.' — '.$invoice->installments
            ->map(fn (PaymentInstallment $row) => PaymentPlanData::labelForInstallment($row))
            ->implode(', ');
    }

    /**
     * #480: the positions as the quote's builder wrote them. A position picked
     * from a payment plan prints the installment's net — its gross split at
     * the line's rate — so the lines add up to what the plan asks for.
     *
     * @return array{lines: list<array<string, string>>, deposits: list<array<string, string>>, totalNet: string, totalVat: string, totalGross: string}
     */
    private function positionLines(Invoice $invoice, InvoiceFigures $figures, StudioSettingsResolver $settings): array
    {
        $smallBusiness = $settings->vatMode() === 'small_business';
        $defaultRate = $settings->effectiveVatRate();
        $free = $invoice->items->whereNull('payment_installment_id')->map(fn (InvoiceItem $item) => $item->asQuoteItem());
        $discount = Quote::computeTotals($free, $invoice->discount_type, $invoice->discount_value)['discount_amount'];

        $lines = $invoice->items->map(function (InvoiceItem $item) use ($smallBusiness, $defaultRate) {
            $rate = (int) ($item->vat_rate ?? $defaultRate);

            if ($item->payment_installment_id !== null) {
                $net = InvoiceFigures::fromGross((int) $item->installment?->amount, $smallBusiness ? 0 : $rate)->net;

                return ['title' => $item->title, 'quantity' => '1', 'unitPrice' => $this->money($net), 'discount' => '', 'total' => $this->money($net), 'vatRate' => $smallBusiness ? '' : VatLines::percent($rate).' %'];
            }

            $line = $item->asQuoteItem();

            return [
                'title' => $item->title,
                'quantity' => (string) $item->quantity,
                'unitPrice' => $this->money((int) $item->unit_price),
                'discount' => $line->discount_amount > 0 ? '−'.$this->money((int) $line->discount_amount) : '',
                'total' => $this->money($line->discountedLineTotal()),
                'vatRate' => $smallBusiness ? '' : VatLines::percent($rate).' %',
            ];
        })->values()->all();

        // § 14 Abs. 4 Nr. 7 UStG: an agreed Minderung is stated, never folded into the prices.
        if ($discount > 0) {
            $lines[] = ['title' => 'Rabatt', 'quantity' => '', 'unitPrice' => '', 'discount' => '', 'total' => '−'.$this->money($discount), 'vatRate' => ''];
        }

        return [
            'lines' => $lines,
            'deposits' => [],
            'totalNet' => $this->money($figures->net),
            'totalVat' => $this->money($figures->vat),
            'totalGross' => $this->money($figures->gross),
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
    private function finalInvoice(Invoice $invoice, StudioSettingsResolver $settings): array
    {
        $final = $invoice->installments->first(fn (PaymentInstallment $row) => $row->isFinal());
        $quote = $final?->acceptedQuote()?->asSeenByClient();

        if ($quote === null) {
            return ['lines' => [], 'deposits' => [], 'totalNet' => '', 'totalVat' => '', 'totalGross' => ''];
        }

        $vat = $quote->vat_mode === 'standard' ? (int) $quote->vat_amount : 0;

        return [
            'lines' => $quote->hasSlider() ? $this->sliderLine($quote) : $this->quoteLines($quote),
            'deposits' => $this->deposits($invoice, $settings),
            'totalNet' => $this->money((int) $quote->net),
            'totalVat' => $this->money($vat),
            'totalGross' => $this->money((int) $quote->net + $vat),
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
                'title' => $item->title,
                'quantity' => (string) $item->quantity,
                'unitPrice' => $item->isIncluded() ? '' : $this->money((int) $item->unit_price),
                'discount' => $item->discount_amount > 0 ? '−'.$this->money((int) $item->discount_amount) : '',
                'total' => $item->isIncluded() ? 'enthalten' : $this->money($item->discountedLineTotal()),
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
                'total' => '−'.$this->money((int) $quote->discount_amount),
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
            'unitPrice' => $this->money((int) $quote->hourly_rate),
            'discount' => '',
            'total' => $this->money($hours * (int) $quote->hourly_rate),
            'vatRate' => VatLines::percent((int) $quote->vat_rate).' %',
        ]];

        if ($quote->discount_amount > 0 && ! $quote->discount_hidden) {
            $lines[] = ['title' => 'Rabatt', 'quantity' => '', 'unitPrice' => '', 'discount' => '', 'total' => '−'.$this->money((int) $quote->discount_amount), 'vatRate' => ''];
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
    private function deposits(Invoice $invoice, StudioSettingsResolver $settings): array
    {
        return Invoice::query()
            // An unsaved preview comes after every invoice that exists.
            ->when($invoice->exists, fn ($query) => $query->where('id', '<', $invoice->id))
            ->whereNull('cancelled_at')
            ->whereHas('installments', fn ($query) => $query->where('payment_plan_id', $invoice->installments->first()?->payment_plan_id))
            ->with(['installments', 'creditNotes', 'project'])
            ->orderBy('id')
            ->get()
            ->map(function (Invoice $earlier) use ($settings) {
                $figures = InvoiceFigures::forInvoice($earlier, $settings)->share($earlier->amount() - $earlier->creditedAmount());

                return [
                    'number' => $earlier->number,
                    'date' => $earlier->issued_at->format('d.m.Y'),
                    'net' => $this->money($figures->net),
                    'vat' => $this->money($figures->vat),
                    'gross' => $this->money($figures->gross),
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
    private function creditNote(?CreditNote $creditNote, ?Project $project, StudioSettingsResolver $settings): array
    {
        $keys = ['number', 'date', 'servicePeriod', 'description', 'reason', 'net', 'vatRate', 'vat', 'gross', 'vatNote', 'invoiceNumber', 'invoiceDate'];

        if ($creditNote === null) {
            return array_fill_keys($keys, '');
        }

        $invoice = $creditNote->invoice;
        $figures = InvoiceFigures::forInvoice($invoice, $settings)->share((int) $creditNote->amount);

        return [
            'number' => $creditNote->number,
            'date' => $creditNote->issued_at->format('d.m.Y'),
            'servicePeriod' => $project?->shoots()->where('is_primary', true)->first()?->date?->format('d.m.Y')
                ?? $invoice->issued_at->format('d.m.Y'),
            'description' => $this->description($invoice),
            'reason' => $creditNote->reason,
            'net' => $this->money($figures->net),
            'vatRate' => $figures->ratePercentage(),
            'vat' => $this->money($figures->vat),
            'gross' => $this->money($figures->gross),
            'vatNote' => $figures->vatNote ?? '',
            'invoiceNumber' => $invoice->number,
            'invoiceDate' => $invoice->issued_at->format('d.m.Y'),
        ];
    }

    /**
     * Validity and the VAT sentence, frozen on the quote at send (Q-04/Q-06)
     * rather than recomputed here: a client reading a quote must see the rule
     * that applied when it went out, not the one the studio switched to
     * afterwards.
     *
     * @return array<string, string>
     */
    private function quote(Project $project, StudioSettingsResolver $settings): array
    {
        $quote = $project->latestQuote;

        if ($quote === null) {
            return ['validUntil' => '', 'vatNote' => ''];
        }

        return [
            'validUntil' => $quote->valid_until?->format('d.m.Y') ?? '',
            // A standard-rate quote froze no note — there is nothing unusual
            // to say — so the sentence is composed from the frozen rate. Only
            // the small-business case carries stored wording.
            'vatNote' => $quote->vat_note
                ?? ($quote->vat_rate === null || $quote->vat_rate === 0
                    ? ''
                    : 'Alle Preise verstehen sich zzgl. '.rtrim(rtrim(number_format($quote->vat_rate / 100, 2, ',', '.'), '0'), ',').' % Umsatzsteuer.'),
        ];
    }

    private function money(int $minorUnits): string
    {
        return Number::currency($minorUnits / 100, in: 'EUR', locale: 'de');
    }
}
