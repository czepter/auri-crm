<?php

namespace App\Quotes\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasHourlyPricing;
use App\Concerns\HasPublicId;
use App\Crm\Models\Contact;
use App\Crm\Models\Project;
use App\Quotes\Enums\DiscountType;
use App\Quotes\Enums\PricingMode;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Support\DiscountCalculator;
use App\Settings\Support\StudioSettingsResolver;
use Database\Factories\Quotes\QuoteFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use RuntimeException;

/**
 * What a project is offered, priced line by line, in minor units throughout.
 *
 * Draft is mutable in place. Q-06's SendQuote is the only writer that ever
 * moves a quote past it — sent_at and the Q-04/Q-03 snapshot columns
 * all land in that one transaction — and from the moment status reads sent,
 * booted()'s guard below refuses every further write except the two
 * transitions Q-06 and Q-07 make from there: sent -> superseded
 * (App\Quotes\Actions\ReviseQuote's predecessor half) and sent -> accepted
 * (App\Quotes\Actions\AcceptQuote).
 */
class Quote extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasHourlyPricing;
    use HasPublicId;

    protected $fillable = ['project_id', 'predecessor_id', 'is_addendum', 'status', 'pricing_mode', 'intro', 'outro', 'currency', 'valid_until', 'sent_at', 'sent_channel', 'number', 'total', 'discount_type', 'discount_value', 'discount_amount', 'net', 'vat_mode', 'vat_rate', 'vat_note', 'vat_amount', 'accepted_at', 'accepted_ip', 'accepted_by_contact_id', 'hourly_rate', 'min_hours', 'max_hours', 'images_per_hour', 'hours', 'time_title', 'time_description', 'recipient_contact_id', 'recipient_street', 'recipient_postcode', 'recipient_city'];

    // Mirrors every column default in memory (the same reason Project's own
    // $attributes gives for portal_visible): without net and discount_amount
    // here, a freshly created()d instance reports them as null until it is
    // reloaded, even though the row itself already holds the column
    // defaults — and Q-06's SendQuote can run against a quote that was never
    // re-fetched after creation.
    protected $attributes = ['status' => 'draft', 'pricing_mode' => 'classic', 'currency' => 'EUR', 'total' => 0, 'discount_amount' => 0, 'net' => 0];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'pricing_mode' => PricingMode::class,
            'valid_until' => 'date',
            'sent_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'discount_type' => DiscountType::class,
            'is_addendum' => 'boolean',
        ];
    }

    /**
     * Refuses a write to an already-sent or already-superseded quote, rather
     * than trusting the UI to hide the form that would have produced one —
     * Q-06's acceptance criterion, proven directly against a raw model
     * write. `updating` (not `saving`) is deliberate: it never fires on the
     * INSERT that creates a quote, so SendQuote's own writes — made while
     * the row still reads draft in the database — go through untouched, the
     * same shape App\Quotes\Models\QuoteItem's own booted() guard uses.
     *
     * A sent quote allows exactly two further writes: status alone, to
     * superseded — what ReviseQuote's predecessor half performs — or status
     * bundled with the acceptance columns together, to accepted — what
     * App\Quotes\Actions\AcceptQuote performs, including Q-12's `hours`
     * and `total`. An accepted or
     * superseded quote allows nothing more, ever.
     */
    protected static function booted(): void
    {
        static::updating(function (self $quote) {
            $original = $quote->getRawOriginal('status');

            if (! in_array($original, [QuoteStatus::Sent->value, QuoteStatus::Accepted->value, QuoteStatus::Superseded->value], true)) {
                return;
            }

            // Q-12: `hours` and `total` join the list by name rather than by
            // softening the guard. A slider quote turns from a configuration
            // into an amount at exactly one moment, and it is this one.
            $allowed = $original === QuoteStatus::Sent->value
                ? ['status', 'accepted_at', 'accepted_ip', 'accepted_by_contact_id', 'hours', 'total']
                : [];
            $blocked = array_diff(array_keys($quote->getDirty()), $allowed);

            if ($blocked !== []) {
                throw new RuntimeException(
                    "Quote {$quote->public_id} is {$original} and immutable: cannot modify [".implode(', ', $blocked).'].'
                );
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order');
    }

    /** Q-11: the shares a plan is materialised from at booking. Empty on a classic quote. */
    public function paymentStages(): HasMany
    {
        return $this->hasMany(QuotePaymentStage::class)->orderBy('sort_order');
    }

    /** The quote this one revises, if any — App\Quotes\Actions\ReviseQuote's own new row. */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'predecessor_id');
    }

    /** The revision that superseded this quote, if any. */
    public function revision(): HasOne
    {
        return $this->hasOne(self::class, 'predecessor_id');
    }

    /** The portal contact who accepted this quote — App\Quotes\Actions\AcceptQuote's own write. Null until then. */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'accepted_by_contact_id');
    }

    public function recipientContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'recipient_contact_id');
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
     * rounded net. App\Quotes\Actions\SendQuote is the only caller, and
     * calls this before flipping status to sent — the quote's own row still
     * reads draft in the database at that point, so booted()'s immutability
     * guard above has nothing to say about it yet.
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
