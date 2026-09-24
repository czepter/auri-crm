<?php

namespace App\Quotes\Actions;

use App\Catalog\Models\Package;
use App\Crm\Models\ClientCase;
use App\Quotes\Data\QuoteData;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;

/**
 * The quote block of an inquiry's overview and of the project's: the current
 * version, the prices of the superseded ones and, while there is no quote, the
 * active packages — the next move is choosing one.
 */
class ReadCaseQuote
{
    /**
     * @return array{quote: ?QuoteData, supersededQuotes: list<array<string, mixed>>, packages: list<array<string, mixed>>}
     */
    public function __invoke(ClientCase $project): array
    {
        // The studio works on drafts too; latestQuote() is the portal's, sent only.
        $quote = $project->quotes()->where('status', '!=', QuoteStatus::Superseded)->with(['items', 'predecessor', 'paymentStages'])->latest('id')->first();

        return [
            'quote' => $quote === null ? null : QuoteData::fromModel($quote),
            // Die ersetzten Fassungen tragen in der Übersicht nur ihren Preis:
            // was in ihnen stand, steht im Angebot selbst.
            'supersededQuotes' => $project->quotes()
                ->where('status', QuoteStatus::Superseded)
                ->latest()
                ->get()
                ->map(fn (Quote $quote) => [
                    'id' => $quote->public_id,
                    'number' => $quote->number,
                    'net' => $quote->net,
                ])->all(),
            'packages' => $quote !== null ? [] : Package::query()
                ->where('is_active', true)
                ->with('items')
                ->orderBy('name')
                ->get()
                ->map(fn (Package $package) => [
                    'id' => $package->public_id,
                    'name' => $package->name,
                    'itemCount' => $package->items->count(),
                    'total' => (int) $package->items->sum(fn ($item) => (int) $item->unit_price * $item->quantity),
                ])->all(),
        ];
    }
}
