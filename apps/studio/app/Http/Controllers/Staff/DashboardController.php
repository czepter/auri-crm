<?php

namespace App\Http\Controllers\Staff;

use App\Crm\Actions\ReadStudioActivity;
use App\Crm\Data\DomainRowData;
use App\Crm\Data\RowActionData;
use App\Crm\Data\TaskRowData;
use App\Crm\Models\Project;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
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
use App\Quotes\Support\QuotePipeline;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Übersicht: the next dates, the figures with last year beside them, what
 * moved, and what is owed.
 *
 * The range (zeitraum) sets the window for the counts and the Verlauf. The
 * pipeline and the instalments are balances of today and ignore it; the
 * pipeline's prior year is the same balance a year ago, from the audit trail.
 */
class DashboardController extends Controller
{
    private const RANGES = ['30' => 30, '90' => 90, 'year' => 365];

    private const UPCOMING = 8;

    private const TASKS = 4;

    private const QUOTES_OUT = 4;

    /** From here on a sent quote without an answer is owed a follow-up. */
    private const FOLLOW_UP_DAYS = 14;

    public function __invoke(
        Request $request,
        ReadInquiryColumn $inquiries,
        ReadPaymentColumn $payments,
        ReadAfterDeliveryColumn $afterDelivery,
        ReadStudioActivity $activity,
    ): Response {
        $viewer = auth('staff')->user();
        $range = (string) $request->query('zeitraum', '30');
        $range = array_key_exists($range, self::RANGES) ? $range : '30';
        $since = now()->subDays(self::RANGES[$range]);
        $yearAgo = now()->subYear();

        $quotes = QuoteAcceptanceRate::counts($since, now());
        $priorQuotes = QuoteAcceptanceRate::counts($since->copy()->subYear(), $yearAgo);
        $priorShoots = $this->shootCount($viewer, $since->copy()->subYear(), $yearAgo);

        return Inertia::render('Uebersicht', [
            'today' => now()->format('Y-m-d'),
            'range' => $range,
            'entries' => $this->upcoming($viewer),
            'pipeline' => [
                'total' => $this->pipeline($viewer),
                'priorYear' => QuotePipeline::asOf($yearAgo, $this->visible($viewer)) ?: null,
            ],
            'payments' => $this->payments($viewer),
            'quotes' => [
                'sent' => $quotes['sent'],
                'accepted' => $quotes['accepted'],
                'priorSent' => $priorQuotes['sent'] ?: null,
                'priorAccepted' => $priorQuotes['sent'] ? $priorQuotes['accepted'] : null,
            ],
            'shoots' => [
                'count' => $this->shootCount($viewer, $since, now()),
                'priorYear' => $priorShoots ?: null,
            ],
            'activity' => $activity($viewer, $since),
            'tasks' => $this->tasks($viewer),
            'openTasks' => Task::query()->open()->visibleTo($viewer)->count(),
            'todo' => $inquiries($viewer)->concat($payments($viewer))->values(),
            'quotesOut' => $this->quotesOut($viewer),
            'galleries' => $afterDelivery($viewer),
        ]);
    }

    private function visible(User $viewer)
    {
        return Project::query()->visibleTo($viewer)->select('projects.id');
    }

    /**
     * Booked shooting days and absences in date order, with no week window: a
     * quiet week still shows what comes next. Absences are read up to the last
     * shooting day listed, or three months when there is none.
     *
     * @return list<array{date: string, time: string|null, what: string, where: string|null, away: bool, href: string|null}>
     */
    private function upcoming(User $viewer): array
    {
        $shoots = Shoot::query()
            ->join('jobs', 'jobs.project_id', '=', 'shoots.project_id')
            ->select('shoots.*')
            ->with('project')
            ->whereIn('shoots.project_id', $this->visible($viewer))
            ->uncancelled()
            ->whereNotNull('date')
            ->whereDate('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->orderBy('call_time')
            ->limit(self::UPCOMING)
            ->get();

        $until = $shoots->last()?->date ?? now()->addMonths(3);

        $entries = $shoots->map(fn (Shoot $shoot) => [
            'date' => $shoot->date->format('Y-m-d'),
            // call_time is a plain time column, not a cast — same trimming NextShootData does.
            'time' => $shoot->call_time === null ? null : substr((string) $shoot->call_time, 0, 5),
            'what' => $shoot->project->name,
            'where' => $shoot->venue_name,
            'away' => false,
            'href' => '/auftraege/'.$shoot->project->public_id.'/shootings',
        ]);

        $absences = Absence::query()
            ->with('user')
            ->visibleTo($viewer)
            ->whereDate('ends_on', '>=', now()->toDateString())
            ->whereDate('starts_on', '<=', Carbon::parse($until)->toDateString())
            ->get()
            ->map(fn (Absence $absence) => [
                'date' => $absence->starts_on->max(now())->format('Y-m-d'),
                'time' => null,
                'what' => $absence->user->name,
                'where' => null,
                'away' => true,
                'href' => null,
            ]);

        return $entries->concat($absences)
            ->sortBy(fn (array $entry) => [$entry['date'], $entry['time'] ?? ''])
            ->take(self::UPCOMING)
            ->values()
            ->all();
    }

    /** Undated tasks sort last: they are owed, but not owed on any given day. */
    private function tasks(User $viewer)
    {
        return TaskRowData::collect(
            Task::query()
                ->open()
                ->visibleTo($viewer)
                ->byDueDate()
                ->with(['project', 'contact', 'assignee'])
                ->limit(self::TASKS)
                ->get()
                ->map(TaskRowData::fromTask(...))
                ->values()
        );
    }

    /** Draft and sent only: accepted is won, superseded and expired are gone. */
    private function pipeline(User $viewer): int
    {
        return (int) Quote::query()
            ->whereIn('status', [QuoteStatus::Draft, QuoteStatus::Sent])
            ->whereIn('project_id', $this->visible($viewer))
            ->sum('total');
    }

    /**
     * What the studio is owed. Late instalments take the tile when there are
     * any; otherwise it shows the running balance and the next due date.
     *
     * @return array{open: int, overdue: int, overdueInstalments: int, instalments: int, overdueSinceDays: int|null, nextDueOn: string|null, nextAmount: int|null}
     */
    private function payments(User $viewer): array
    {
        $mine = fn ($query) => $query->whereHas(
            'plan.job',
            fn ($query) => $query->whereIn('project_id', $this->visible($viewer)),
        );

        $scheduled = PaymentInstallment::query()
            ->where('status', PaymentInstallmentStatus::Scheduled)
            ->tap($mine);

        $late = (clone $scheduled)->whereDate('due_on', '<', now()->toDateString());
        $oldest = (clone $late)->orderBy('due_on')->value('due_on');
        $next = (clone $scheduled)->whereDate('due_on', '>=', now()->toDateString())->orderBy('due_on')->first();

        return [
            'open' => (int) (clone $scheduled)->sum('amount'),
            'overdue' => (int) (clone $late)->sum('amount'),
            'overdueInstalments' => (clone $late)->count(),
            'instalments' => PaymentInstallment::query()->tap($mine)->count(),
            'overdueSinceDays' => $oldest === null ? null : (int) Carbon::parse($oldest)->diffInDays(now()),
            'nextDueOn' => $next === null ? null : Carbon::parse($next->due_on)->format('Y-m-d'),
            'nextAmount' => $next?->amount,
        ];
    }

    private function shootCount(User $viewer, CarbonInterface $from, CarbonInterface $to): int
    {
        return Shoot::query()
            ->join('jobs', 'jobs.project_id', '=', 'shoots.project_id')
            ->whereIn('shoots.project_id', $this->visible($viewer))
            ->uncancelled()
            ->whereBetween('shoots.date', [$from->toDateString(), $to->toDateString()])
            ->count();
    }

    /**
     * Sent quotes still waiting on the client, oldest first. Past the
     * follow-up mark the age is the emphasis and the row offers the quote.
     *
     * @return Collection<int, DomainRowData>
     */
    private function quotesOut(User $viewer): Collection
    {
        return Quote::query()
            ->where('status', QuoteStatus::Sent)
            ->whereIn('project_id', $this->visible($viewer))
            ->with('project')
            ->orderBy('sent_at')
            ->limit(self::QUOTES_OUT)
            ->get()
            ->map(function (Quote $quote) {
                $days = (int) Carbon::parse($quote->sent_at)->diffInDays(now());
                $age = $days === 1 ? '1 Tag' : "{$days} Tagen";
                $late = $days >= self::FOLLOW_UP_DAYS;

                return new DomainRowData(
                    projectId: $quote->project->public_id,
                    who: $quote->project->name,
                    what: $late ? 'Verschickt vor' : "Verschickt vor {$age}",
                    emphasis: $late ? $age : null,
                    whatTail: ' · '.Number::currency($quote->total / 100, 'EUR', 'de', 0),
                    action: $late ? RowActionData::link('Nachfassen', '/auftraege/'.$quote->project->public_id.'/angebote') : null,
                    hot: $late,
                );
            })
            ->values();
    }
}
