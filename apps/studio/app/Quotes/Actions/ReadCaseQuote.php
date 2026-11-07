<?php

namespace App\Quotes\Actions;

use App\Crm\Models\ClientCase;
use App\Quotes\Data\QuoteData;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;

/**
 * The quote block of an inquiry's overview and of the project's: the current
 * version and the prices of the superseded ones.
 */
class ReadCaseQuote
{
    /**
     * @return array{quote: ?QuoteData, supersededQuotes: list<array<string, mixed>>}
     */
    public function __invoke(ClientCase $project): array
    {
        $quote = $project->currentQuote()->with(['items', 'predecessor', 'paymentStages'])->first();

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
        ];
    }
}
