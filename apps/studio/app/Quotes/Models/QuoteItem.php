<?php

namespace App\Quotes\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
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

    protected $fillable = ['title', 'description', 'quantity', 'unit_price', 'sort_order'];

    protected $attributes = ['quantity' => 1, 'sort_order' => 0];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
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

    protected static function newFactory(): Factory
    {
        return QuoteItemFactory::new();
    }
}
