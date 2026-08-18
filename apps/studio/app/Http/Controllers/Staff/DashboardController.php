<?php

namespace App\Http\Controllers\Staff;

use App\Crm\Data\LeadCardData;
use App\Crm\Data\NextShootData;
use App\Crm\Data\TaskRowData;
use App\Crm\Enums\LeadStage;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Engagement\Models\Thread;
use App\Gallery\Actions\ReadAfterDeliveryColumn;
use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\User;
use App\Payments\Actions\ReadPaymentColumn;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Quotes\Actions\ReadInquiryColumn;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Support\QuoteAcceptanceRate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Übersicht: an action worklist, not a status report.
 *
 * C-04 (#238) rebuilt it on the CRM's own four-column grid, which changed what
 * this method has to answer for. Two rules came with it:
 *
 * **No metric without its proof** (C-05, #239). Every figure in the first row
 * ships the cases behind it, one dot each, so the number can be checked rather
 * than believed. `4 Shootings im September` is struck from the draft for
 * failing that, and nothing here may be added without its dots.
 *
 * **Nothing summarised in a footer.** A line reading "2 Anfragen kollidieren
 * mit dem 14.06." names neither which nor where; the conflict is a mark on the
 * row that has it, and it leads into the resolution. Same reason the calendar
 * preview is gone: "Diese Woche" is the week, not a sentence about it.
 *
 * Three deliberate omissions survive from the first build, every one because
 * there is no fact behind the draft's clause rather than because nobody got
 * to it:
 *
 * - The draft's "nie fällig gestellt" payment row. PaymentInstallmentStatus
 *   ::Invoiced exists on the enum but nothing in this codebase ever writes it
 *   (see that enum's docblock), so there is no state to detect and no action
 *   to end it with.
 * - The draft's "— Album nie angeboten" clause on the gallery row. No album
 *   concept exists to have been offered; ReadAfterDeliveryColumn states the
 *   expiry, which is the fact, and leaves the clause off.
 * - The draft's "Review-Bitten laufen automatisch" footer under Galerien. No
 *   seeded rule sends review requests, so the line would be this screen
 *   reporting an automation that does not run.
 *
 * The production board came off this screen with C-02 (#236): every project in
 * production is a row at /projekte, which is a list, and this screen is a day.
 */
class DashboardController extends Controller
{
    /** Five decisions. The rest is on the project it belongs to. */
    private const DECISIONS = 5;

    public function __invoke(
        ReadInquiryColumn $inquiries,
        ReadPaymentColumn $payments,
        ReadAfterDeliveryColumn $afterDelivery,
    ): Response {
        $viewer = auth('staff')->user();
        $hero = $this->nextShoot($viewer);

        return Inertia::render('Uebersicht', [
            'today' => now()->format('Y-m-d'),
            'nextShoot' => $hero === null ? null : NextShootData::fromShoot($hero),
            'week' => $this->week($viewer),
            'tasks' => $this->tasks($viewer),
            'openTasks' => Task::query()->open()->whereIn('project_id', Project::query()->visibleTo($viewer)->select('projects.id'))->count(),
            'inquiries' => $inquiries($viewer),
            'payments' => $payments($viewer),
            'galleries' => $afterDelivery($viewer),
            'newLeads' => $this->newLeads(),
            'pipeline' => $this->pipeline(),
            // The proof under Pipeline: one dot per open lead, carrying its
            // own stage so the screen can shade it. The stage travels rather
            // than a colour — which shade a stage gets is the sheet's
            // business, not this method's.
            'pipelineStages' => $this->pipelineStages(),
            'overdue' => $this->overdue(),
            'quotes' => QuoteAcceptanceRate::countsForTrailingYear(),
            'unanswered' => $this->unanswered(),
        ]);
    }

    /**
     * One stage per open lead, in funnel order — not the column's alphabetical
     * order, which puts `contacted` before `new` and makes the row's shading
     * jump for no reason a reader could follow.
     *
     * @return list<string>
     */
    private function pipelineStages(): array
    {
        $order = array_column(LeadStage::cases(), 'value');

        return Lead::query()->open()->pluck('stage')
            ->map(fn (LeadStage $stage) => $stage->value)
            ->sortBy(fn (string $stage) => array_search($stage, $order, true))
            ->values()
            ->all();
    }

    /**
     * The next shooting day the studio still has to work: today or later, not
     * cancelled, and only for a project that is actually booked — an unbooked
     * date is a request, not a commitment.
     *
     * Returns the model rather than NextShootData: week() below needs the
     * hero's own date to leave it out of the list, and re-deriving that from a
     * formatted string would be the same query wearing a worse type.
     */
    private function nextShoot(User $viewer): ?Shoot
    {
        return Shoot::query()
            ->join('jobs', 'jobs.project_id', '=', 'shoots.project_id')
            ->select('shoots.*')
            ->with(['project', 'crew.user', 'tasks'])
            ->whereIn('shoots.project_id', Project::query()->visibleTo($viewer)->select('projects.id'))
            ->uncancelled()
            ->whereNotNull('date')
            ->whereDate('date', '>=', now()->startOfDay())
            ->orderBy('date')
            ->first();
    }

    /**
     * "Diese Woche": the seven days ahead, shooting days and absences in one
     * list, in time order.
     *
     * An absence is an entry, dimmed on its own day — not a message. `Nina
     * abwesend` announced above the week said nothing about which day it
     * costs, which is the only thing anyone reads that line for.
     *
     * @return list<array{when: string, what: string, away: bool}>
     */
    private function week(User $viewer): array
    {
        $until = now()->addWeek();

        $shoots = Shoot::query()
            ->join('jobs', 'jobs.project_id', '=', 'shoots.project_id')
            ->select('shoots.*')
            ->with('project')
            ->whereIn('shoots.project_id', Project::query()->visibleTo($viewer)->select('projects.id'))
            ->uncancelled()
            ->whereNotNull('date')
            ->whereDate('date', '>=', now()->startOfDay())
            ->whereDate('date', '<=', $until->toDateString())
            ->get()
            ->map(fn (Shoot $shoot) => [
                'date' => $shoot->date->format('Y-m-d'),
                // call_time is a plain time column, not a cast — same
                // trimming NextShootData does with it.
                'time' => $shoot->call_time === null ? null : substr((string) $shoot->call_time, 0, 5),
                'what' => $shoot->project->name,
                'away' => false,
            ]);

        $absences = Absence::query()
            ->with('user')
            ->whereDate('ends_on', '>=', now()->toDateString())
            ->whereDate('starts_on', '<=', $until->toDateString())
            ->get()
            ->map(fn (Absence $absence) => [
                'date' => $absence->starts_on->max(now())->format('Y-m-d'),
                'time' => null,
                // The name alone. Whoever renders it says "abwesend" — the
                // German lives in de.json, not split across a PHP lang file
                // nobody thinks to look in.
                'what' => $absence->user->name,
                'away' => true,
            ]);

        return $shoots->concat($absences)
            ->sortBy(fn (array $entry) => [$entry['date'], $entry['time'] ?? ''])
            ->values()
            ->map(fn (array $entry) => [
                'when' => $entry['date'].($entry['time'] === null ? '' : ' '.$entry['time']),
                'what' => $entry['what'],
                'away' => $entry['away'],
            ])
            ->all();
    }

    /**
     * What the studio owes and has not done, most urgent first. Six read, five
     * shown beside the two other decision sources — the count of what is left
     * is on the panel's own header.
     *
     * Undated tasks sort last: they are owed, but not owed on any given day.
     */
    private function tasks(User $viewer)
    {
        return TaskRowData::collect(
            Task::query()
                ->open()
                ->whereIn('project_id', Project::query()->visibleTo($viewer)->select('projects.id'))
                ->byDueDate()
                ->with('project')
                ->limit(self::DECISIONS)
                ->get()
                ->map(TaskRowData::fromTask(...))
                ->values()
        );
    }

    /**
     * The newest open inquiries, as they arrived — occasion, names, when and
     * from where. Their date conflicts ride along on the card, because a
     * doubled date is a mark on the row that has it.
     */
    private function newLeads()
    {
        return LeadCardData::collect(
            Lead::query()
                ->open()
                ->with(['project.contacts', 'project.shoots'])
                ->latest()
                ->limit(3)
                ->get()
                ->map(fn (Lead $lead) => LeadCardData::fromLead($lead, []))
                ->values()
        );
    }

    /**
     * What is still in play. Draft and sent only: accepted is won and belongs
     * to a revenue number, superseded and expired are gone. Minor units all
     * the way to the browser, formatted once on the screen.
     */
    private function pipeline(): int
    {
        return (int) Quote::query()
            ->whereIn('status', [QuoteStatus::Draft, QuoteStatus::Sent])
            ->sum('total');
    }

    /**
     * Overdue money with the proof beside it: how many instalments are late
     * out of how many the studio is owed at all, and how long the oldest has
     * been late. The dots are open against settled, which is the only reading
     * of "1 von 4 Raten offen" a caller can check.
     *
     * @return array{total: int, open: int, all: int, sinceDays: int|null}
     */
    private function overdue(): array
    {
        $late = PaymentInstallment::query()
            ->where('status', PaymentInstallmentStatus::Scheduled)
            ->whereDate('due_on', '<', now()->toDateString());

        $oldest = (clone $late)->orderBy('due_on')->value('due_on');

        return [
            'total' => (int) (clone $late)->sum('amount'),
            'open' => (clone $late)->count(),
            'all' => PaymentInstallment::query()->count(),
            'sinceDays' => $oldest === null ? null : (int) $oldest->diffInDays(now()),
        ];
    }

    /**
     * Threads whose newest message came from the client — unbeantwortet, not
     * ungelesen. ThreadParticipant carries no last_read_at and this does not
     * add one: an unanswered thread is a fact about the conversation, and
     * only that is something the studio owes. Whether a person has opened it
     * is a fact about the person, not this count.
     *
     * `latestMessage` is Thread::hasOne()->latestOfMany() (see that model),
     * so a thread with no messages has no latestMessage and whereHas simply
     * never matches it — no raw SQL, no null-comparison edge to get wrong on
     * a thread that has not had its first message yet.
     */
    private function unanswered(): int
    {
        return Thread::query()
            ->whereHas('latestMessage', fn ($query) => $query->whereNotNull('contact_id'))
            ->count();
    }
}
