<?php

namespace App\Quotes\Data;

use App\Quotes\Models\Quote;
use App\Quotes\Support\QuoteRange;
use App\Quotes\Support\VatLines;
use App\Settings\Support\StudioSettingsResolver;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class QuoteData extends Data
{
    /**
     * @param  list<QuoteItemData>  $items
     * @param  list<array{rate: int, net: int, vat: int}>  $vatLines
     */
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
        // Q-04: frozen by Q-06's send onto the quote's own columns — read
        // straight off the row once populated, so a later change to the
        // tenant's VAT mode alters nothing an already-sent quote shows.
        // Never null here, though: a draft has nothing frozen yet, so
        // fromModel() fills these with what sending it right now would
        // produce (Quote::computeVat()) — a live preview, not a value this
        // quote's row carries.
        public string $vatMode,
        public int $vatRate,
        public ?string $vatNote,
        public int $vatAmount,
        // #412: das Entgelt je Steuersatz (§ 14 Abs. 4 Nr. 7 UStG). Ein
        // Angebot mit einem Satz hat genau einen Eintrag; leer bei
        // Kleinunternehmerregelung, wo kein Satz auszuweisen ist.
        public array $vatLines,
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
        public int $timeSortOrder = 0,
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
        // Der Freigabe-Link (QuoteShareController) — unabhängig von
        // portal_visible und ohne Login, share_token ist sein ganzer
        // Zugriffsschutz. Wie in App\Quotes\Notifications\QuoteSent:
        // config('portal.url') statt route(), die Route existiert im
        // Admin-Prozess nicht.
        public ?string $portalUrl = null,
    ) {}

    public static function fromModel(Quote $quote): self
    {
        // Frozen once sent; for a draft, computeVat() gives the screen the
        // same shape from the tenant's current settings instead of nulls.
        $vat = $quote->vat_mode !== null
            ? ['vat_mode' => $quote->vat_mode, 'vat_rate' => $quote->vat_rate, 'vat_note' => $quote->vat_note, 'vat_amount' => $quote->vat_amount]
            : Quote::computeVat($quote->net, app(StudioSettingsResolver::class));

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
            vatMode: $vat['vat_mode'],
            vatRate: $vat['vat_rate'],
            vatNote: $vat['vat_note'],
            vatAmount: $vat['vat_amount'],
            vatLines: $vat['vat_mode'] === 'standard'
                ? VatLines::fromItems($quote->items, (int) $vat['vat_rate'], $quote->net)
                : [],
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
            timeSortOrder: $quote->time_sort_order,
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
            portalUrl: config('portal.url')."/angebot/{$quote->share_token}/teilen",
        );
    }
}
