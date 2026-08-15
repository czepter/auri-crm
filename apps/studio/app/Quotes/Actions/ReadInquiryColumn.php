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
use Illuminate\Support\Collection;

/**
 * Mockup 01's *Anfragen & Angebote* column: what the studio owes on an offer
 * it has already made.
 *
 * Derived rather than maintained, like ReadProductionBoard — a quote accepted
 * this morning drops off the column on the next read because there is nothing
 * else for it to do.
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

        $visible = Project::query()->visibleTo($viewer)->select('projects.id');

        return $this->chasedQuotes($visible)
            ->concat($this->acceptedWithoutContract($visible))
            ->concat($this->unsignedContracts($visible))
            ->values();
    }

    /**
     * A sent quote running out with the reminders spent. The count in the row
     * is the real number of AutomationRuns fired against the project, which is
     * what makes "2× erinnert, keine Reaktion" a fact rather than a phrase.
     *
     * @return Collection<int, DomainRowData>
     */
    private function chasedQuotes($visible): Collection
    {
        return Quote::query()
            ->where('status', QuoteStatus::Sent)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '>=', now()->toDateString())
            ->whereDate('valid_until', '<=', now()->addDays(self::EXPIRY_WINDOW_DAYS)->toDateString())
            ->whereIn('project_id', $visible)
            ->with('project.contacts')
            ->get()
            ->map(function (Quote $quote) {
                $nudges = $this->nudgeCount($quote->project_id);

                if ($nudges === 0) {
                    return null;
                }

                return new DomainRowData(
                    projectId: $quote->project->public_id,
                    who: $quote->project->name,
                    what: 'Angebot still,',
                    emphasis: 'läuft '.$quote->valid_until->format('d.m.').' ab',
                    whatTail: sprintf('· %d× erinnert, keine Reaktion', $nudges),
                    action: RowActionData::tel('Anrufen', $quote->project->primaryContact()?->phone),
                    hot: true,
                );
            })
            ->filter()
            ->values();
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
     * @return Collection<int, DomainRowData>
     */
    private function acceptedWithoutContract($visible): Collection
    {
        return Quote::query()
            ->where('status', QuoteStatus::Accepted)
            ->whereIn('project_id', $visible)
            ->whereDoesntHave('project.contracts')
            ->with('project.contacts')
            ->get()
            ->map(fn (Quote $quote) => new DomainRowData(
                projectId: $quote->project->public_id,
                who: $quote->project->name,
                what: 'angenommen '.$quote->accepted_at?->format('d.m.').' —',
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
     * @return Collection<int, DomainRowData>
     */
    private function unsignedContracts($visible): Collection
    {
        return Contract::query()
            ->where('status', ContractStatus::Rendered)
            ->whereDoesntHave('signature')
            ->where('created_at', '<=', now()->subDays(self::UNSIGNED_AFTER_DAYS))
            ->whereIn('project_id', $visible)
            ->with('project.contacts')
            ->get()
            ->map(fn (Contract $contract) => new DomainRowData(
                projectId: $contract->project->public_id,
                who: $contract->project->name,
                what: 'Vertrag',
                emphasis: $contract->created_at->diffInDays().' Tage unsigniert',
                whatTail: null,
                action: RowActionData::tel('Anrufen', $contract->project->primaryContact()?->phone),
            ));
    }
}
