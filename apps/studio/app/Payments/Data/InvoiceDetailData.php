<?php

namespace App\Payments\Data;

use App\Payments\Models\Invoice;
use App\Payments\Models\InvoiceItem;
use App\Payments\Support\InvoiceFigures;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The invoice as a page of its own: positions, sums, where it came from and what happened to it. */
#[TypeScript]
class InvoiceDetailData extends Data
{
    /**
     * @param  list<array{title: string, description: ?string, quantity: int, unitPrice: int, total: int}>  $items
     * @param  list<array{kind: string, on: string, amount: ?int}>  $events  newest first
     */
    public function __construct(
        public InvoiceRowData $row,
        public ?string $recipient,
        public ?string $quoteId,
        public ?string $quoteNumber,
        public array $items,
        public int $net,
        public int $vat,
        public int $gross,
        public string $vatRate,
        public array $events,
    ) {}

    public static function fromModel(Invoice $invoice): self
    {
        $invoice->loadMissing(['items', 'installments', 'creditNotes', 'contact', 'case', 'views']);

        $figures = InvoiceFigures::forInvoice($invoice);
        $quote = $invoice->case === null ? null : Quote::query()
            ->where('case_id', $invoice->case_id)
            ->where('status', QuoteStatus::Accepted)
            ->latest('accepted_at')
            ->first();

        return new self(
            row: InvoiceRowData::fromModel($invoice),
            recipient: $invoice->contact?->name,
            quoteId: $quote?->public_id,
            quoteNumber: $quote?->number,
            items: $invoice->items->map(fn (InvoiceItem $item) => [
                'title' => $item->title,
                'description' => $item->description,
                'quantity' => (int) $item->quantity,
                'unitPrice' => (int) $item->unit_price,
                'total' => $item->asQuoteItem()->lineTotal(),
            ])->all(),
            net: $figures->net,
            vat: $figures->vat,
            gross: $figures->gross,
            vatRate: $figures->ratePercentage(),
            events: self::events($invoice),
        );
    }

    /** @return list<array{kind: string, on: string, amount: ?int}> */
    private static function events(Invoice $invoice): array
    {
        $events = collect([['kind' => 'created', 'on' => $invoice->issued_at, 'amount' => null]]);

        if ($invoice->sent_at !== null) {
            $events->push(['kind' => 'sent', 'on' => $invoice->sent_at, 'amount' => null]);
        }

        $opened = $invoice->views->where('counts', true)->min('viewed_at');

        if ($opened !== null) {
            $events->push(['kind' => 'viewed', 'on' => $opened, 'amount' => null]);
        }

        foreach ($invoice->installments->whereNotNull('paid_on') as $row) {
            $events->push(['kind' => 'paid', 'on' => $row->paid_on, 'amount' => (int) $row->paid_amount]);
        }

        foreach ($invoice->creditNotes as $note) {
            $events->push(['kind' => 'credited', 'on' => $note->issued_at, 'amount' => (int) $note->amount]);
        }

        if ($invoice->cancelled_at !== null) {
            $events->push(['kind' => 'cancelled', 'on' => $invoice->cancelled_at, 'amount' => null]);
        }

        return $events
            ->sortBy('on')
            ->reverse()
            ->map(fn (array $event) => [...$event, 'on' => $event['on']->format('Y-m-d')])
            ->values()
            ->all();
    }
}
