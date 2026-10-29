<?php

namespace App\Quotes\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\HasHourlyPricing;
use App\Concerns\HasPublicId;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Contact;
use App\Media\Concerns\HasShowcaseImages;
use App\Models\StudioSettings;
use App\Quotes\Enums\DiscountType;
use App\Quotes\Enums\PricingMode;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Support\DiscountCalculator;
use App\Quotes\Support\PaymentStageSplitter;
use App\Quotes\Support\QuoteRange;
use App\Quotes\Support\VatLines;
use Database\Factories\Quotes\QuoteFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
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
    use HasShowcaseImages;

    protected $fillable = ['case_id', 'predecessor_id', 'is_addendum', 'status', 'pricing_mode', 'intro', 'outro', 'currency', 'valid_until', 'sent_at', 'sent_channel', 'number', 'total', 'discount_type', 'discount_value', 'discount_amount', 'discount_hidden', 'net', 'vat_mode', 'vat_rate', 'vat_note', 'vat_amount', 'accepted_at', 'accepted_ip', 'accepted_by_contact_id', 'hourly_rate', 'min_hours', 'max_hours', 'images_per_hour', 'hours', 'time_title', 'time_description', 'time_sort_order', 'recipient_contact_id', 'recipient_street', 'recipient_postcode', 'recipient_city', 'showcase_paths'];

    // The bearer credential QuoteShareController checks — never worth
    // serialising alongside the rest of the model, the same call
    // HasPublicId's own $hidden makes for the raw id.
    protected $hidden = ['share_token'];

    // Mirrors every column default in memory (the same reason Project's own
    // $attributes gives for portal_visible): without net and discount_amount
    // here, a freshly created()d instance reports them as null until it is
    // reloaded, even though the row itself already holds the column
    // defaults — and Q-06's SendQuote can run against a quote that was never
    // re-fetched after creation.
    protected $attributes = ['status' => 'draft', 'pricing_mode' => 'classic', 'currency' => 'EUR', 'total' => 0, 'discount_amount' => 0, 'discount_hidden' => false, 'net' => 0, 'time_sort_order' => 0];

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
            'discount_hidden' => 'boolean',
            'showcase_paths' => 'array',
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
        // The share link's whole credential (QuoteShareController) — a
        // separate value from public_id because public_id already leaves the
        // server toward an authenticated contact elsewhere; this one leaves
        // toward whoever holds the link, no session required.
        static::creating(function (self $quote) {
            $quote->share_token ??= Str::random(40);
        });

        static::updating(function (self $quote) {
            $original = $quote->getRawOriginal('status');

            if (! in_array($original, [QuoteStatus::Sent->value, QuoteStatus::Accepted->value, QuoteStatus::Superseded->value], true)) {
                return;
            }

            // Q-12: `hours` and `total` join the list by name rather than by
            // softening the guard. A slider quote turns from a configuration
            // into an amount at exactly one moment, and it is this one.
            // #442: the chosen amount replaces the preselection's net and VAT,
            // and with it the discount taken off that amount.
            $sliderAcceptance = $quote->hasSlider() && $quote->status === QuoteStatus::Accepted
                ? ['discount_amount', 'net', 'vat_amount']
                : [];
            $allowed = $original === QuoteStatus::Sent->value
                ? ['status', 'accepted_at', 'accepted_ip', 'accepted_by_contact_id', 'hours', 'total', ...$sliderAcceptance]
                : [];
            $blocked = array_diff(array_keys($quote->getDirty()), $allowed);

            if ($blocked !== []) {
                throw new RuntimeException(
                    "Quote {$quote->public_id} is {$original} and immutable: cannot modify [".implode(', ', $blocked).'].'
                );
            }
        });
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ClientCase::class);
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

    /**
     * The stages in euros, against what the client pays in total: the same
     * split CreatePaymentPlan charges at booking.
     *
     * @return list<array{label: string, amount: int}>
     */
    public function paymentSplit(): array
    {
        $base = $this->net + ($this->vat_mode === 'standard' ? ($this->vat_amount ?? 0) : 0);

        return PaymentStageSplitter::split($base, $this->paymentStages);
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
     */
    public function recalculateTotal(): void
    {
        // Q-15: a slider quote has no total before acceptance, but snapshotVat()
        // taxes net, so net follows the studio's preselection until AcceptQuote.
        if ($this->hasSlider()) {
            $this->update(['net' => QuoteRange::preselected($this->load('items'))]);

            return;
        }

        $this->update(self::computeTotals($this->items()->get(), $this->discount_type, $this->discount_value));
    }

    /**
     * The pure sums recalculateTotal() persists — pulled out so
     * App\Quotes\Http\Controllers\QuoteController::preview() can run the
     * same arithmetic on an unsaved quote's request-built items without a
     * second, drifting copy of it.
     *
     * total stays the gross sum (an included item's line total is zero —
     * QuoteItem::lineTotal() — which is what keeps it out of this sum).
     * subtotal sums each item's discountedLineTotal() instead: lineTotal()
     * less that line's own already-resolved discount, still excluding every
     * included line for the same reason. The quote-level discount then
     * resolves once against that subtotal (DiscountCalculator::resolve()),
     * and net is subtotal minus that single resolved amount — the one
     * rounding this whole computation performs, on the total, not per line.
     *
     * @param  iterable<QuoteItem>  $items
     * @return array{total: int, discount_amount: int, net: int}
     */
    public static function computeTotals(iterable $items, ?DiscountType $discountType, ?int $discountValue): array
    {
        $items = collect($items);
        $subtotal = $items->sum(fn (QuoteItem $item) => $item->discountedLineTotal());
        $discountAmount = DiscountCalculator::resolve($subtotal, $discountType, $discountValue);

        return [
            'total' => $items->sum(fn (QuoteItem $item) => $item->lineTotal()),
            'discount_amount' => $discountAmount,
            'net' => $subtotal - $discountAmount,
        ];
    }

    /**
     * The quote as a client reads it. With discount_hidden, every discount is
     * folded into the line prices: each rate's net from VatLines is shared
     * out over that rate's lines, so the per-rate nets and the VAT a renderer
     * recomputes from the lines match what the row froze. Never saved.
     *
     * ponytail: unit_price is floored to amount / quantity, so "3 × 68,52 €"
     * can trail the line amount by up to quantity − 1 cents.
     */
    public function asSeenByClient(): self
    {
        if (! $this->discount_hidden || $this->hasSlider()) {
            return $this;
        }

        $rate = (int) $this->vat_rate;
        $nets = collect(VatLines::fromItems($this->items, $rate, (int) $this->net))->pluck('net', 'rate');
        $items = $this->items->map(fn (QuoteItem $item) => clone $item);

        foreach ($items->filter(fn (QuoteItem $item) => $item->discountedLineTotal() > 0)->groupBy(fn (QuoteItem $item) => $item->vat_rate ?? $rate) as $key => $group) {
            $amounts = DiscountCalculator::spread($group->map(fn (QuoteItem $item) => $item->discountedLineTotal())->all(), $nets[$key] ?? 0);

            foreach ($group as $index => $item) {
                $item->unit_price = intdiv($amounts[$index], $item->quantity);
                $item->discount_type = null;
                $item->discount_value = null;
                $item->discount_amount = $item->lineTotal() - $amounts[$index];
            }
        }

        $client = clone $this;
        $client->forceFill(['total' => $this->net, 'discount_type' => null, 'discount_value' => null, 'discount_amount' => 0]);
        $client->setRelation('items', $items);

        return $client;
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
     * StudioSettings::current() again — that is what makes a later change to
     * the tenant's VAT mode alter nothing an already-sent quote renders.
     */
    public function snapshotVat(): void
    {
        $this->update(self::computeVat($this->net, $this->items()->get()));
    }

    /**
     * #412: the Entgelt by tax rate — § 14 Abs. 4 Nr. 7 UStG. One line at the
     * quote's own rate for every quote whose lines carry no rate of their
     * own, which is every quote written before this existed; empty under the
     * small-business rule, where there is no rate to break out.
     *
     * @return list<array{rate: int, net: int, vat: int}>
     */
    public function vatLines(): array
    {
        if ($this->vat_mode !== 'standard') {
            return [];
        }

        return VatLines::fromItems($this->items, (int) $this->vat_rate, (int) $this->net);
    }

    /**
     * The pure VAT snapshot snapshotVat() persists — see computeTotals()'s
     * docblock for why this is split out the same way.
     *
     * vat_amount reuses DiscountCalculator::resolve() rather than a second
     * rounding rule: a VAT rate is a percentage of a base exactly like a
     * discount is, round-half-up, computed off net (Q-03's one rounding),
     * never off an unrounded intermediate line.
     *
     * #412: with lines at their own rates it is the sum over the rate lines
     * instead — the same rounding, applied once per rate rather than once,
     * so the total still matches the rows a client reads.
     *
     * @param  iterable<QuoteItem>  $items
     * @return array{vat_mode: string, vat_rate: int, vat_note: ?string, vat_amount: int}
     */
    public static function computeVat(int $net, iterable $items = []): array
    {
        $mode = StudioSettings::current()->vat_mode;
        $rate = StudioSettings::current()->effectiveVatRate();

        return [
            'vat_mode' => $mode,
            'vat_rate' => $rate,
            // Only small_business prints anything — see the mockup and
            // QuoteData: standard shows a computed VAT line instead, so it
            // has no use for the note text.
            'vat_note' => $mode === 'small_business' ? StudioSettings::current()->vat_note : null,
            // § 19 UStG: no rate is charged, so a line's own rate is ignored
            // rather than broken out against a base that carries no tax.
            'vat_amount' => $mode === 'small_business'
                ? 0
                : collect(VatLines::fromItems($items, $rate, $net))->sum('vat'),
        ];
    }

    protected static function newFactory(): Factory
    {
        return QuoteFactory::new();
    }
}
