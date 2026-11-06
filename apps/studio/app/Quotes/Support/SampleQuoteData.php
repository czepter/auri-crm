<?php

namespace App\Quotes\Support;

use App\Crm\Enums\ProjectType;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Quotes\Enums\DiscountType;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;

/**
 * A quote that has never touched the database — exactly what Einstellungen
 * → Belege & Fristen needs to preview a chosen App\Quotes\Enums\QuoteLayout
 * without a real project, contact or quote existing yet. Same shape as
 * QuoteController::preview()'s in-memory draft, minus a real project: every
 * relation is set with setRelation() rather than saved.
 */
class SampleQuoteData
{
    public static function build(): Quote
    {
        $project = new ClientCase(['name' => 'Musterauftrag', 'type' => ProjectType::Other]);
        $contact = new Contact(['name' => 'Muster GmbH', 'street' => 'Musterstraße 1', 'postcode' => '12345', 'city' => 'Musterstadt']);

        $items = collect([
            new QuoteItem([
                'title' => 'Fotoreportage',
                'description' => 'Begleitung von 2 Terminen, Bereitstellung in 2 Online-Galerien',
                'quantity' => 2,
                'unit_price' => 25000,
            ]),
            new QuoteItem([
                'title' => 'Zusatzleistung',
                'description' => 'Zusätzliches Gruppenfoto im Anschluss an den Termin',
                'quantity' => 1,
                'unit_price' => null,
            ]),
        ])->each(fn (QuoteItem $item) => $item->discount_amount = $item->resolveDiscountAmount());

        $quote = new Quote([
            'number' => 'AN-'.now()->year.'-14',
            'sent_at' => now(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 1000,
        ]);
        $quote->setRelation('case', $project);
        $quote->setRelation('recipientContact', $contact);
        $quote->setRelation('items', $items);
        $quote->setRelation('paymentStages', collect());
        $quote->fill(Quote::computeTotals($items, $quote->discount_type, $quote->discount_value));
        $quote->fill(Quote::computeVat($quote->net, $items));

        return $quote;
    }
}
