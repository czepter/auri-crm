<?php

namespace App\Quotes\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Project;
use App\Quotes\Enums\DiscountType;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Support\DiscountCalculator;
use App\Settings\Support\StudioSettingsResolver;
use Database\Factories\Quotes\QuoteFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a project is offered, priced line by line, in minor units throughout.
 *
 * Only QuoteStatus::Draft is reachable through anything this story builds —
 * see that enum — so a quote here is mutated in place rather than replaced.
 * Q-07's "supersede" story is what makes a quote immutable once sent.
 */
class Quote extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['project_id', 'status', 'currency', 'valid_until', 'total', 'discount_type', 'discount_value', 'discount_amount', 'net', 'vat_mode', 'vat_rate', 'vat_note', 'vat_amount'];

    protected $attributes = ['status' => 'draft', 'currency' => 'EUR', 'total' => 0];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'valid_until' => 'date',
            'discount_type' => DiscountType::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order');
    }

    /**
     * The only writer of total, discount_amount and net. Sums fresh from the
     * database rather than trusting a possibly-stale relation, so it is safe
     * to call right after items()->createMany().
     *
     * total stays the gross sum (an included item's line total is zero —
     * QuoteItem::lineTotal() — which is what keeps it out of this sum).
     * subtotal sums each item's discountedLineTotal() instead: lineTotal()
     * less that line's own already-resolved discount, still excluding every
     * included line for the same reason. The quote-level discount then
     * resolves once against that subtotal (DiscountCalculator::resolve()),
     * and net is subtotal minus that single resolved amount — the one
     * rounding this whole computation performs, on the total, not per line.
     */
    public function recalculateTotal(): void
    {
        $items = $this->items()->get();
        $subtotal = $items->sum(fn (QuoteItem $item) => $item->discountedLineTotal());
        $discountAmount = DiscountCalculator::resolve($subtotal, $this->discount_type, $this->discount_value);

        $this->update([
            'total' => $items->sum(fn (QuoteItem $item) => $item->lineTotal()),
            'discount_amount' => $discountAmount,
            'net' => $subtotal - $discountAmount,
        ]);
    }

    /**
     * Freezes vat_mode, vat_rate, vat_note and vat_amount onto this quote
     * from the tenant's current settings and this quote's own already-
     * rounded net. Q-06's only intended caller, at send time — nothing here
     * runs today (same "ships unwired" shape Q-05's allocator shipped in).
     *
     * Once these four columns are populated, QuoteData::fromModel() reads
     * them straight off the row and never falls through to
     * StudioSettingsResolver again — that is what makes a later change to
     * the tenant's VAT mode alter nothing an already-sent quote renders.
     *
     * vat_amount reuses DiscountCalculator::resolve() rather than a second
     * rounding rule: a VAT rate is a percentage of a base exactly like a
     * discount is, round-half-up, computed off net (Q-03's one rounding),
     * never off an unrounded intermediate line.
     */
    public function snapshotVat(StudioSettingsResolver $settings): void
    {
        $mode = $settings->vatMode();
        $rate = $settings->effectiveVatRate();

        $this->update([
            'vat_mode' => $mode,
            'vat_rate' => $rate,
            // Only small_business prints anything — see the mockup and
            // QuoteData: standard shows a computed VAT line instead, so it
            // has no use for the note text.
            'vat_note' => $mode === 'small_business' ? $settings->vatNote() : null,
            'vat_amount' => DiscountCalculator::resolve($this->net, DiscountType::Percentage, $rate),
        ]);
    }

    protected static function newFactory(): Factory
    {
        return QuoteFactory::new();
    }
}
