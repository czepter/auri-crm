<?php

namespace App\Http\Controllers\Staff;

use App\Crm\Actions\ReadProductionBoard;
use App\Crm\Data\NextShootData;
use App\Crm\Data\TaskRowData;
use App\Crm\Models\Lead;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Engagement\Models\Thread;
use App\Gallery\Actions\ReadAfterDeliveryColumn;
use App\Http\Controllers\Controller;
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
 * Mockup 01's four domain blocks and its four-tile KPI row all read from real
 * data now. Nowhere on this screen is a number invented to fill a clause;
 * that is the failure clickdummy §6 records for it.
 *
 * Three deliberate omissions, every one of them because there is no fact
 * behind the mockup's clause rather than because nobody got to it:
 *
 * - The mockup's "nie fällig gestellt" payment row. PaymentInstallmentStatus
 *   ::Invoiced exists on the enum but nothing in this codebase ever writes it
 *   (see that enum's docblock), so there is no state to detect and no action
 *   to end it with.
 * - The mockup's "— Album nie angeboten" clause on the gallery row. No album
 *   concept exists to have been offered; ReadAfterDeliveryColumn states the
 *   expiry, which is the fact, and leaves the clause off.
 * - The mockup's "Review-Bitten laufen automatisch" footer under Nach der
 *   Lieferung. No seeded rule sends review requests, so the line would be
 *   this screen reporting an automation that does not run.
 *
 * And one deviation that is not an omission: the "n unbeantwortet" count in
 * the header states its figure without linking anywhere. Threads exist only
 * per-project, at /auftraege/{project}/nachrichten; routes/admin.php has no
 * cross-project inbox, and the mockup's own href is "#", so no destination
 * was ever named. Inventing one is the same failure as inventing a number.
 *
 * The Nächste Shootings block is the one column with two footers: the owner
 * asked to keep S-01's open-task-count line and add the mockup's calendar
 * preview beneath it rather than choose between them, so `upcomingShoots`
 * feeds a second `.blkfoot` instead of replacing the first.
 */
class DashboardController extends Controller
{
    public function __invoke(
        ReadProductionBoard $board,
        ReadInquiryColumn $inquiries,
        ReadPaymentColumn $payments,
        ReadAfterDeliveryColumn $afterDelivery,
    ): Response {
        $viewer = auth('staff')->user();
        $expectedByMonthEnd = $this->expectedByMonthEnd();
        $hero = $this->nextShoot($viewer);

        return Inertia::render('Uebersicht', [
            'today' => now()->format('Y-m-d'),
            'openLeads' => Lead::query()->open()->count(),
            'newLeadsThisWeek' => Lead::query()->open()->where('created_at', '>=', now()->subWeek())->count(),
            'nextShoot' => $hero === null ? null : NextShootData::fromShoot($hero),
            'upcomingShoots' => $this->upcomingShoots($viewer, $hero),
            'production' => $board($viewer),
            'tasks' => $this->tasks($viewer),
            'openTasks' => Task::query()->open()->whereIn('project_id', Project::query()->visibleTo($viewer)->select('projects.id'))->count(),
            'inquiries' => $inquiries($viewer),
            'payments' => $payments($viewer),
            'afterDelivery' => $afterDelivery($viewer),
            'pipeline' => $this->pipeline(),
            'overdueTotal' => $this->overdueTotal(),
            'expectedByMonthEndTotal' => $expectedByMonthEnd['total'],
            'expectedByMonthEndCount' => $expectedByMonthEnd['count'],
            'acceptanceRate' => $this->acceptanceRate(),
            'unanswered' => $this->unanswered(),
        ]);
    }

    /**
     * The next shooting day the studio still has to work: today or later, not
     * cancelled, and only for a project that is actually booked — an unbooked
     * date is a request, not a commitment.
     *
     * Returns the model rather than NextShootData: upcomingShoots() below
     * needs the hero's own date to filter by, and re-deriving that from
     * NextShootData's formatted string would be the same query wearing a
     * worse type.
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
     * The calendar footer's preview: what is booked after the hero shoot, so
     * the block hints at what the calendar holds without opening it. Same
     * booked/uncancelled/dated rule as the hero; "after" is `date >
     * $hero->date` rather than `>=` plus an id exclusion, since the hero is
     * by construction the earliest such shoot — nothing dated on or before
     * it can be anything other than the hero itself.
     *
     * No hero, no preview: "after the next one" names nothing when there is
     * no next one, so this returns empty rather than every future shoot.
     *
     * @return list<array{label: string, date: string}>
     */
    private function upcomingShoots(User $viewer, ?Shoot $hero): array
    {
        if ($hero === null) {
            return [];
        }

        return Shoot::query()
            ->join('jobs', 'jobs.project_id', '=', 'shoots.project_id')
            ->select('shoots.*')
            ->whereIn('shoots.project_id', Project::query()->visibleTo($viewer)->select('projects.id'))
            ->uncancelled()
            ->whereNotNull('date')
            ->whereDate('date', '>', $hero->date)
            ->orderBy('date')
            ->limit(2)
            ->get()
            ->map(fn (Shoot $shoot) => [
                'label' => $shoot->label ?? $shoot->kind->label(),
                'date' => $shoot->date->format('Y-m-d'),
            ])
            ->all();
    }

    /**
     * What the studio owes and has not done, most urgent first, six at most.
     *
     * The six is the cap, not one row per project: mockup 01 lists Meier &
     * Kowalski twice in the same block, because two different things are owed
     * on that wedding and each needs its own button. Collapsing to one row per
     * project reads well on a busy board and empties the screen on a studio
     * with a single project in production — which is every studio in its first
     * month, and the state this screen gets judged in.
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
                ->limit(6)
                ->get()
                ->map(TaskRowData::fromTask(...))
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

    private function overdueTotal(): int
    {
        return (int) PaymentInstallment::query()
            ->where('status', PaymentInstallmentStatus::Scheduled)
            ->whereDate('due_on', '<', now()->toDateString())
            ->sum('amount');
    }

    /**
     * What the überfällig tile's sub-line and the Zahlungen footer both name:
     * money not yet late, still due before the month runs out. Scheduled
     * only — an installment already overdue has its own tile and its own row
     * on the board, and counting it here too would report the same money
     * under two different failures.
     *
     * @return array{total: int, count: int}
     */
    private function expectedByMonthEnd(): array
    {
        $scheduled = PaymentInstallment::query()
            ->where('status', PaymentInstallmentStatus::Scheduled)
            ->whereDate('due_on', '>=', now()->toDateString())
            ->whereDate('due_on', '<=', now()->endOfMonth()->toDateString());

        return [
            'total' => (int) (clone $scheduled)->sum('amount'),
            'count' => (clone $scheduled)->count(),
        ];
    }

    /**
     * S-13 shows this same percentage on the Auswertungen screen, so the
     * query moved to App\Quotes\Support\QuoteAcceptanceRate and both screens
     * read it from there. Its own doc comment carries the two rules that
     * matter — keyed on `sent_at`, null rather than zero on an empty
     * denominator.
     */
    private function acceptanceRate(): ?int
    {
        return QuoteAcceptanceRate::trailingTwelveMonths();
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
