<?php

namespace App\Quotes\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasPublicId;
use App\Crm\Models\Project;
use App\Quotes\Enums\QuoteStatus;
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

    protected $fillable = ['project_id', 'status', 'currency', 'valid_until', 'total'];

    protected $attributes = ['status' => 'draft', 'currency' => 'EUR', 'total' => 0];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'valid_until' => 'date',
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
     * The only writer of total. Sums each item's line total fresh from the
     * database rather than trusting a possibly-stale relation, so it is safe
     * to call right after items()->createMany(). An included item's line
     * total is zero (QuoteItem::lineTotal()), which is what keeps it out of
     * this sum — the same rule Q-03 will read the discountable base from.
     */
    public function recalculateTotal(): void
    {
        $this->update([
            'total' => $this->items()->get()->sum(fn (QuoteItem $item) => $item->lineTotal()),
        ]);
    }

    protected static function newFactory(): Factory
    {
        return QuoteFactory::new();
    }
}
