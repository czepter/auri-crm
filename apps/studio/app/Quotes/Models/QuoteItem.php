<?php

namespace App\Quotes\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Quotes\Enums\DiscountType;
use App\Quotes\Support\DiscountCalculator;
use Database\Factories\Quotes\QuoteItemFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One priced — or included — line on a quote. Shaped after Q-01's
 * PackageItem on purpose: unit_price null is "Enthaltene Leistung", the same
 * distinction, so a quote seeded from a package (Package::copyItems())
 * carries it straight through with nothing translated in between.
 *
 * No package_id: a package is a starting point, never a live link (see
 * Package's own docblock), so nothing here can drift when the package it was
 * copied from is later edited or deactivated.
 */
class QuoteItem extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['title', 'description', 'quantity', 'unit_price', 'sort_order', 'discount_type', 'discount_value', 'selectable', 'selected', 'outcome', 'image_path'];

    protected $attributes = ['quantity' => 1, 'sort_order' => 0];

    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'selectable' => 'boolean',
            'selected' => 'boolean',
        ];
    }

    /**
     * discount_amount is never mass-assigned — this is the only writer, so
     * "resolved to an amount at entry" (Q-03) actually holds: it is
     * recomputed from discount_type/discount_value every time the row is
     * saved, never left stale and never re-derived a second way elsewhere.
     */
    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $item->discount_amount = $item->resolveDiscountAmount();
        });
    }

    /**
     * The same resolution the saving() hook above applies on write — pulled
     * out so App\Quotes\Http\Controllers\QuoteController::preview() can put
     * it on an unsaved item, which the hook never touches.
     */
    public function resolveDiscountAmount(): int
    {
        return DiscountCalculator::resolve($this->lineTotal(), $this->discount_type, $this->discount_value);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * Q-09: a line the client switches in the portal. Neither a fixed
     * position nor an included one — it counts toward the total only once
     * `selected` says so.
     */
    public function isSelectable(): bool
    {
        return $this->selectable;
    }

    /** Enthaltene Leistung: part of what was already paid for, not a price this line invents. */
    public function isIncluded(): bool
    {
        return $this->unit_price === null;
    }

    /**
     * Zero for an included line. This is the rule that keeps it out of
     * Quote::recalculateTotal(), and the same rule Q-03's discountable base
     * will reuse — a line with nothing charged on it has nothing to discount.
     */
    public function lineTotal(): int
    {
        return $this->isIncluded() ? 0 : $this->quantity * $this->unit_price;
    }

    /**
     * lineTotal() less this line's own discount — the base
     * Quote::recalculateTotal() sums into the quote-level discount's base.
     * Zero for an included line, same as lineTotal(): discount_amount
     * resolves to zero against a zero base, so nothing extra is needed to
     * keep an included line out of the discount calculation too.
     */
    public function discountedLineTotal(): int
    {
        return $this->lineTotal() - $this->discount_amount;
    }

    protected static function newFactory(): Factory
    {
        return QuoteItemFactory::new();
    }
}
