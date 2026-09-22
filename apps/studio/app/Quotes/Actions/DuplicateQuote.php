<?php

namespace App\Quotes\Actions;

use App\Numbering\Actions\AllocateDocumentNumber;
use App\Numbering\Enums\DocumentType;
use App\Quotes\Models\Quote;
use App\Settings\Support\StudioSettingsResolver;
use Illuminate\Support\Facades\DB;

/**
 * A new, independent draft carrying another quote's positions and terms —
 * never its history. status, sent_at/sent_channel, predecessor, vat snapshot
 * and accepted_* stay behind: this is a starting point for a new offer, not
 * a clone of one already sent.
 */
class DuplicateQuote
{
    public function __construct(
        private readonly AllocateDocumentNumber $allocate,
        private readonly StudioSettingsResolver $settings,
    ) {}

    public function __invoke(Quote $source): Quote
    {
        $source->loadMissing(['items', 'paymentStages']);

        return DB::transaction(function () use ($source) {
            $allocation = ($this->allocate)(documentType: DocumentType::Quote);

            $quote = $source->project->quotes()->create([
                'pricing_mode' => $source->pricing_mode,
                'number' => $allocation['number'],
                'valid_until' => now()->addDays($this->settings->quoteValidityDays())->toDateString(),
                'intro' => $source->intro,
                'outro' => $source->outro,
                'discount_type' => $source->discount_type,
                'discount_value' => $source->discount_value,
                'discount_hidden' => $source->discount_hidden,
                'hourly_rate' => $source->hourly_rate,
                'min_hours' => $source->min_hours,
                'max_hours' => $source->max_hours,
                'images_per_hour' => $source->images_per_hour,
                'hours' => $source->hours,
                'time_title' => $source->time_title,
                'time_description' => $source->time_description,
                'time_sort_order' => $source->time_sort_order,
                'recipient_contact_id' => $source->recipient_contact_id,
                'recipient_street' => $source->recipient_street,
                'recipient_postcode' => $source->recipient_postcode,
                'recipient_city' => $source->recipient_city,
            ]);

            $quote->update(['showcase_paths' => $source->copyShowcasePaths($quote->showcaseDirectory())]);

            $quote->items()->createMany(
                $source->items->map(fn ($item) => $item->only([
                    'title', 'description', 'quantity', 'unit_price', 'sort_order',
                    'discount_type', 'discount_value', 'selectable', 'selected', 'outcome', 'image_path',
                ]))->all()
            );

            $quote->paymentStages()->createMany(
                $source->paymentStages->map(fn ($stage) => $stage->only(['percent', 'label', 'minimum', 'sort_order']))->all()
            );

            $quote->recalculateTotal();

            return $quote;
        });
    }
}
