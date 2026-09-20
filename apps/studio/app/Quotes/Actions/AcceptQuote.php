<?php

namespace App\Quotes\Actions;

use App\Crm\Actions\AdvanceLeadStage;
use App\Crm\Enums\StageAnchor;
use App\Crm\Models\Contact;
use App\Notifications\Actions\NotifyStudio;
use App\Notifications\Studio\QuoteAccepted;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Models\QuoteItem;
use App\Quotes\Support\QuoteRange;
use App\Quotes\Support\VatLines;
use App\Support\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The client's own state change, mirrored in shape off Q-06's SendQuote but
 * triggered by the acting contact rather than staff. The whole point of this
 * story: the re-check below runs against the row as it stands right now, at
 * submit — never against whatever a screen rendered minutes or tabs ago. A
 * quote superseded by a revision, or past its own valid_until, is rejected
 * here even when an earlier GET on the same quote had nothing to object to.
 *
 * Idempotent: a doubled request (a retried POST, two tabs) finds the
 * quote already accepted and returns it, rather than failing on the
 * booted() guard's second write or writing a second acceptance.
 *
 * Q-12 gives it a second job. A quote with an hourly rate is a
 * configuration until somebody accepts it; this is where the client's choice
 * is written down and where it stops being a range and becomes an amount.
 * What the frontend sends is a proposal — hours outside the range, or a line
 * the studio never made switchable, are refused here rather than quietly
 * bent into shape. The same stance EnsurePortalCapability already takes on
 * the accept_quotes switch.
 */
class AcceptQuote
{
    public function __construct(
        private readonly NotifyStudio $notifyStudio,
        private readonly AdvanceLeadStage $leads,
    ) {}

    /**
     * @param  list<string>  $selectedItemIds  public_ids of the blocks the client switched on
     * @param  ?string  $ip  null when staff record a yes given by mail or phone
     * @param  ?CarbonInterface  $at  when the yes was given, for one entered after the fact
     */
    public function __invoke(
        Quote $quote,
        Contact $contact,
        ?string $ip,
        ?int $hours = null,
        array $selectedItemIds = [],
        ?CarbonInterface $at = null,
    ): Quote {
        if ($quote->status === QuoteStatus::Accepted) {
            return $quote;
        }

        if ($quote->status === QuoteStatus::Superseded) {
            throw ValidationException::withMessages(['quote' => __('portal.quote_superseded')]);
        }

        $expired = $quote->valid_until !== null && $quote->valid_until->copy()->endOfDay()->lt($at ?? now());

        if ($quote->status !== QuoteStatus::Sent || $expired) {
            throw ValidationException::withMessages(['quote' => __('portal.quote_not_valid')]);
        }

        $configuration = $quote->hasSlider()
            ? $this->configuration($quote, $hours, $selectedItemIds)
            : [];

        DB::transaction(function () use ($quote, $contact, $ip, $configuration, $at) {
            $quote->update([
                'status' => QuoteStatus::Accepted,
                'accepted_at' => $at ?? now(),
                'accepted_ip' => $ip,
                'accepted_by_contact_id' => $contact->id,
                ...$configuration['quote'] ?? [],
            ]);

            // Written after the quote, inside the same transaction: what the
            // client switched on is part of the acceptance, not a separate
            // edit that could survive a rolled-back one.
            foreach ($configuration['selected'] ?? [] as $itemId => $selected) {
                QuoteItem::query()->whereKey($itemId)->update(['selected' => $selected]);
            }

            // actor is null, not $contact — AuditLog::actor_id is a foreign
            // key to staff users, and a Contact is never one.
            AuditLogger::record(
                event: 'quote.accepted',
                context: ['project' => $quote->project->public_id, 'contact' => $contact->public_id],
                subject: $quote->public_id,
                projectId: $quote->project_id,
                occurredAt: $at,
            );

            $this->leads->reach($quote->project->lead, StageAnchor::QuoteAccepted, at: $at);
        });

        $accepted = $quote->fresh();

        // Outside the transaction: a mail that has already left cannot be
        // rolled back. The early returns above never reach here, so a doubled
        // request announces the acceptance once.
        ($this->notifyStudio)(new QuoteAccepted($accepted));

        return $accepted;
    }

    /**
     * Q-12: the client's proposal, checked and turned into the columns the
     * acceptance writes.
     *
     * @param  list<string>  $selectedItemIds
     * @return array{quote: array<string, mixed>, selected: array<int, bool>}
     */
    private function configuration(Quote $quote, ?int $hours, array $selectedItemIds): array
    {
        $hours ??= $quote->hours ?? $quote->min_hours ?? 0;

        if (
            ($quote->min_hours !== null && $hours < $quote->min_hours)
            || ($quote->max_hours !== null && $hours > $quote->max_hours)
        ) {
            throw ValidationException::withMessages(['hours' => __('portal.quote_hours_out_of_range')]);
        }

        $selectable = QuoteRange::selectable($quote);
        $chosen = $selectable->whereIn('public_id', $selectedItemIds);

        // A line the studio never made switchable cannot be switched. Sent
        // as a set difference rather than per line: naming which one was
        // refused would tell a caller what to send next time.
        if ($chosen->count() !== count(array_unique($selectedItemIds))) {
            throw ValidationException::withMessages(['items' => __('portal.quote_item_not_selectable')]);
        }

        $total = QuoteRange::subtotalFor($quote, $hours, $chosen->pluck('id'));
        $net = QuoteRange::totalFor($quote, $hours, $chosen->pluck('id'));

        return [
            'quote' => [
                'hours' => $hours,
                'total' => $total,
                'discount_amount' => $total - $net,
                'net' => $net,
                // At the rate frozen at send, never today's setting.
                'vat_amount' => $quote->vat_mode === 'small_business'
                    ? 0
                    : collect(VatLines::fromItems($quote->items, (int) $quote->vat_rate, $net))->sum('vat'),
            ],
            'selected' => $selectable
                ->mapWithKeys(fn (QuoteItem $item) => [$item->id => $chosen->contains('id', $item->id)])
                ->all(),
        ];
    }
}
