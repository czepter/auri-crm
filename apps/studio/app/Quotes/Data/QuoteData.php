<?php

namespace App\Quotes\Data;

use App\Quotes\Models\Quote;
use App\Quotes\Support\QuoteRange;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class QuoteData extends Data
{
    /** @param list<QuoteItemData> $items */
    public function __construct(
        public string $id,
        public ?string $predecessorId,
        // Nachtrag statt Revision: der Vorgänger bleibt angenommen, dieses
        // Angebot führt nur, was dazukommt.
        public bool $isAddendum,
        public ?string $predecessorNumber,
        public string $status,
        public string $statusLabel,
        public string $pricingMode,
        public string $currency,
        public ?string $validUntil,
        public ?string $number,
        public ?string $sentAt,
        // §4.6: beide Wege enden im selben Zustand, die Kopfzeile sagt welcher.
        public ?string $sentChannel,
        public int $total,
        public ?string $discountType,
        public ?int $discountValue,
        public int $discountAmount,
        public int $net,
        // Q-04: null on every quote until Q-06 sends it and calls
        // Quote::snapshotVat() — read straight off the quote's own columns,
        // never re-derived from the tenant's live settings, so this is the
        // one place the screen has to look regardless of what the studio's
        // VAT mode is set to right now.
        public ?string $vatMode,
        public ?int $vatRate,
        public ?string $vatNote,
        public ?int $vatAmount,
        // Anschreiben, roh mit Merge-Feldern — aufgelöst wird erst beim
        // Rendern (App\Quotes\Actions\RenderQuotePdf), damit der Schirm
        // bearbeitet, was gespeichert ist.
        public ?string $intro,
        public ?string $outro,
        public array $items,
        public ?int $hourlyRate = null,
        public ?int $minHours = null,
        public ?int $maxHours = null,
        public ?int $imagesPerHour = null,
        public ?int $hours = null,
        public ?string $timeTitle = null,
        public ?string $timeDescription = null,
        // Q-15: vor der Zusage hat ein Regler-Angebot keinen Betrag, sondern
        // einen Bereich — berechnet, nie gespeichert.
        public ?int $rangeMin = null,
        public ?int $rangeMax = null,
        /** @var list<array{id: string, percent: int, label: string, minimum: int}> */
        public array $paymentStages = [],
        public ?string $recipientContact = null,
        public ?string $recipientStreet = null,
        public ?string $recipientPostcode = null,
        public ?string $recipientCity = null,
        // Wie in App\Quotes\Notifications\QuoteSent: config('portal.url') und
        // nur, wenn der Kunde das Portal überhaupt sehen darf.
        public ?string $portalUrl = null,
    ) {}

    public static function fromModel(Quote $quote): self
    {
        return new self(
            id: $quote->public_id,
            // The predecessor is a Quote row too, referenced here the same
            // way every other cross-model FK this Data layer exposes is —
            // by public_id, never the raw predecessor_id column.
            predecessorId: $quote->predecessor?->public_id,
            isAddendum: $quote->is_addendum,
            predecessorNumber: $quote->predecessor?->number,
            status: $quote->status->value,
            statusLabel: $quote->status->label(),
            pricingMode: $quote->pricing_mode->value,
            currency: $quote->currency,
            validUntil: $quote->valid_until?->format('Y-m-d'),
            number: $quote->number,
            sentAt: $quote->sent_at?->toIso8601String(),
            sentChannel: $quote->sent_channel,
            total: $quote->total,
            discountType: $quote->discount_type?->value,
            discountValue: $quote->discount_value,
            discountAmount: $quote->discount_amount,
            net: $quote->net,
            vatMode: $quote->vat_mode,
            vatRate: $quote->vat_rate,
            vatNote: $quote->vat_note,
            vatAmount: $quote->vat_amount,
            intro: $quote->intro,
            outro: $quote->outro,
            items: $quote->items->map(QuoteItemData::fromModel(...))->all(),
            hourlyRate: $quote->hourly_rate,
            minHours: $quote->min_hours,
            maxHours: $quote->max_hours,
            imagesPerHour: $quote->images_per_hour,
            hours: $quote->hours,
            timeTitle: $quote->time_title,
            timeDescription: $quote->time_description,
            rangeMin: $quote->hasSlider() ? QuoteRange::min($quote) : null,
            rangeMax: $quote->hasSlider() ? QuoteRange::max($quote) : null,
            paymentStages: $quote->paymentStages->map(fn ($stage) => [
                'id' => $stage->public_id,
                'percent' => $stage->percent,
                'label' => $stage->label,
                'minimum' => $stage->minimum,
            ])->all(),
            recipientContact: $quote->recipientContact?->public_id,
            recipientStreet: $quote->recipient_street,
            recipientPostcode: $quote->recipient_postcode,
            recipientCity: $quote->recipient_city,
            portalUrl: $quote->project->portal_visible
                ? config('portal.url')."/projekte/{$quote->project->public_id}/angebot"
                : null,
        );
    }
}
