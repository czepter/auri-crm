<?php

namespace App\Quotes\Screens;

use App\Automation\Models\AutomationRun;
use App\Crm\Data\DomainRowData;
use App\Crm\Data\RowActionData;
use App\Crm\Models\ClientCase;
use App\Models\User;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Support\QuoteRange;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

/**
 * Mockup 01's *Anfragen & Angebote* column: what the studio owes on an offer
 * it has already made.
 *
 * Derived rather than maintained — a quote accepted this morning drops off the
 * column on the next read because there is nothing else for it to do.
 *
 * The first row is gated on the automation trail, not on silence alone. The
 * design's decision 2 is the reason: a quote whose reminder has not gone out
 * yet is the machine's work, and putting it here would make this a status
 * report about things already in hand.
 */
class ReadInquiryColumn
{
    private const EXPIRY_WINDOW_DAYS = 14;

    private const FOLLOW_UP_RULE = 'quote-follow-up';

    /**
     * @return Collection<int, DomainRowData>
     */
    public function __invoke(?User $viewer = null): Collection
    {
        $viewer ??= auth('staff')->user();

        return $this->chasedQuotes($viewer);
    }

    /**
     * A sent quote running out with the reminders spent. The count in the row
     * is the real number of AutomationRuns fired against the project, which is
     * what makes "2× erinnert, keine Reaktion" a fact rather than a phrase.
     *
     * Ordered by soonest expiry first: valid_until is the deadline the row is
     * about, so the quote closest to lapsing is the most urgent one to call.
     *
     * @return Collection<int, DomainRowData>
     */
    private function chasedQuotes(?User $viewer): Collection
    {
        return Quote::query()
            ->where('status', QuoteStatus::Sent)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '>=', now()->toDateString())
            ->whereDate('valid_until', '<=', now()->addDays(self::EXPIRY_WINDOW_DAYS)->toDateString())
            ->when($viewer !== null, fn ($query) => $query->whereIn(
                'case_id',
                ClientCase::query()->visibleTo($viewer)->select('cases.id'),
            ))
            ->with(['case', 'items'])
            ->withExists(['views as opened' => fn ($views) => $views->where('counts', true)])
            ->orderBy('valid_until')
            ->get()
            ->map(function (Quote $quote) {
                $nudges = $this->nudgeCount($quote->case_id);

                if ($nudges === 0) {
                    return null;
                }

                return new DomainRowData(
                    projectId: $quote->case->public_id,
                    who: $quote->case->name,
                    what: $this->worth($quote).' still,',
                    emphasis: 'läuft '.$quote->valid_until->format('d.m.Y').' ab',
                    whatTail: sprintf($quote->opened ? '· %d× erinnert, Angebot geöffnet, keine Antwort' : '· %d× erinnert, keine Reaktion', $nudges),
                    action: RowActionData::tel('Anrufen', $quote->case->primaryContact()?->phone),
                    hot: true,
                );
            })
            ->filter()
            ->values();
    }

    /**
     * Q-15: what the row calls the offer. A quote with a slider has no sum
     * before it is accepted, it has a range — computed from the same
     * configuration every time rather than kept in a second column that
     * would part ways with the first the moment somebody edits a block.
     */
    private function worth(Quote $quote): string
    {
        if (! $quote->hasSlider()) {
            return 'Angebot';
        }

        return sprintf(
            'Angebot %s–%s',
            $this->money(QuoteRange::min($quote)),
            $this->money(QuoteRange::max($quote)),
        );
    }

    private function money(int $minorUnits): string
    {
        return Number::currency($minorUnits / 100, in: 'EUR', locale: 'de', precision: 0);
    }

    /**
     * How often the machine has already asked. Kept here rather than as a
     * scope on AutomationRun: this is the only column that counts runs —
     * Zahlungen reads overdue_notified_at and Nach der Lieferung asks a
     * different question entirely — and a shared scope over one call site is
     * an abstraction with nothing to abstract.
     */
    private function nudgeCount(int $projectId): int
    {
        return AutomationRun::query()
            ->whereHas('rule', fn ($query) => $query->where('key', self::FOLLOW_UP_RULE))
            ->where('anchorable_type', (new ClientCase)->getMorphClass())
            ->where('anchorable_id', $projectId)
            ->count();
    }
}
