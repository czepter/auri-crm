<?php

namespace App\Quotes\Actions;

use App\Automation\Models\AutomationRun;
use App\Contracts\Enums\ContractStatus;
use App\Contracts\Models\Contract;
use App\Crm\Data\DomainRowData;
use App\Crm\Data\RowActionData;
use App\Crm\Models\Project;
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

    private const UNSIGNED_AFTER_DAYS = 4;

    private const FOLLOW_UP_RULE = 'quote-follow-up';

    /**
     * @return Collection<int, DomainRowData>
     */
    public function __invoke(?User $viewer = null): Collection
    {
        $viewer ??= auth('staff')->user();

        return $this->chasedQuotes($viewer)
            ->concat($this->acceptedWithoutContract($viewer))
            ->concat($this->unsignedContracts($viewer))
            ->values();
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
                'project_id',
                Project::query()->visibleTo($viewer)->select('projects.id'),
            ))
            ->with(['project', 'items'])
            ->orderBy('valid_until')
            ->get()
            ->map(function (Quote $quote) {
                $nudges = $this->nudgeCount($quote->project_id);

                if ($nudges === 0) {
                    return null;
                }

                return new DomainRowData(
                    projectId: $quote->project->public_id,
                    who: $quote->project->name,
                    what: $this->worth($quote).' still,',
                    emphasis: 'läuft '.$quote->valid_until->format('d.m.Y').' ab',
                    whatTail: sprintf('· %d× erinnert, keine Reaktion', $nudges),
                    action: RowActionData::tel('Anrufen', $quote->project->primaryContact()?->phone),
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
            ->where('anchorable_type', (new Project)->getMorphClass())
            ->where('anchorable_id', $projectId)
            ->count();
    }

    /**
     * Ordered by oldest acceptance first: there is no deadline here, only how
     * long the studio has already left "Vertrag fehlt" standing, so the
     * longest-waiting acceptance is the most urgent one to chase.
     *
     * @return Collection<int, DomainRowData>
     */
    private function acceptedWithoutContract(?User $viewer): Collection
    {
        return Quote::query()
            ->where('status', QuoteStatus::Accepted)
            ->when($viewer !== null, fn ($query) => $query->whereIn(
                'project_id',
                Project::query()->visibleTo($viewer)->select('projects.id'),
            ))
            ->whereDoesntHave('project.contracts')
            ->with('project')
            ->orderBy('accepted_at')
            ->get()
            ->map(fn (Quote $quote) => new DomainRowData(
                projectId: $quote->project->public_id,
                who: $quote->project->name,
                what: 'angenommen '.$quote->accepted_at?->format('d.m.Y').' —',
                emphasis: 'Vertrag fehlt',
                whatTail: null,
                action: RowActionData::link(
                    'Vertrag senden',
                    "/auftraege/{$quote->project->public_id}/vertrag",
                ),
                hot: true,
            ));
    }

    /**
     * ContractStatus describes the render, not a dispatch — there is no `sent`
     * to test against — so a rendered contract with no signature, aged past
     * the window, is what "unsigniert" means here.
     *
     * Ordered by oldest render first: the contract that has been waiting on a
     * signature the longest is the most urgent one to call about.
     *
     * @return Collection<int, DomainRowData>
     */
    private function unsignedContracts(?User $viewer): Collection
    {
        return Contract::query()
            ->where('status', ContractStatus::Rendered)
            ->whereDoesntHave('signature')
            ->where('created_at', '<=', now()->subDays(self::UNSIGNED_AFTER_DAYS))
            ->when($viewer !== null, fn ($query) => $query->whereIn(
                'project_id',
                Project::query()->visibleTo($viewer)->select('projects.id'),
            ))
            ->with('project')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Contract $contract) => new DomainRowData(
                projectId: $contract->project->public_id,
                who: $contract->project->name,
                what: 'Vertrag',
                emphasis: (int) $contract->created_at->diffInDays().' Tage unsigniert',
                whatTail: null,
                action: RowActionData::tel('Anrufen', $contract->project->primaryContact()?->phone),
            ));
    }
}
