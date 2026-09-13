<?php

namespace App\Quotes\Support;

use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Quotes\Enums\DiscountType;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Settings\Support\StudioSettingsResolver;

/**
 * A quote that has never touched the database — exactly what Einstellungen
 * → Belege & Fristen needs to preview a chosen App\Quotes\Enums\QuoteLayout
 * without a real project, contact or quote existing yet. Same shape as
 * QuoteController::preview()'s in-memory draft, minus a real project: every
 * relation is set with setRelation() rather than saved.
 */
class SampleQuoteData
{
    public static function build(StudioSettingsResolver $settings): Quote
    {
        $project = new Project(['name' => 'Erstkommunion 2026']);
        $contact = new Contact(['name' => 'Pfarrgemeinde St. Johann', 'street' => 'Kirchplatz 3', 'postcode' => '07743', 'city' => 'Jena']);

        $items = collect([
            new QuoteItem([
                'title' => 'Reportage Erstkommunion 2026',
                'description' => 'Begleitung von 2 Erstkommunionterminen, Bereitstellung in 2 Online-Galerien',
                'quantity' => 2,
                'unit_price' => 25000,
            ]),
            new QuoteItem([
                'title' => 'Familienfoto-Ergänzung',
                'description' => 'Zusätzliches Gruppenfoto im Anschluss an den Termin',
                'quantity' => 1,
                'unit_price' => null,
            ]),
        ])->each(fn (QuoteItem $item) => $item->discount_amount = $item->resolveDiscountAmount());

        $quote = new Quote([
            'number' => '2026-014',
            'valid_until' => '2026-09-26',
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 1000,
        ]);
        $quote->setRelation('project', $project);
        $quote->setRelation('recipientContact', $contact);
        $quote->setRelation('items', $items);
        $quote->setRelation('paymentStages', collect());
        $quote->fill(Quote::computeTotals($items, $quote->discount_type, $quote->discount_value));
        $quote->fill(Quote::computeVat($quote->net, $settings));

        return $quote;
    }
}
