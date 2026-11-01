<?php

namespace App\Staff\Http\Controllers;

use App\Crm\Actions\OpenLeads;
use App\Crm\Data\TaskRowData;
use App\Crm\Models\ClientCase;
use App\Crm\Models\Shoot;
use App\Crm\Models\Task;
use App\Crm\Screens\OpenCases;
use App\Crm\Screens\ReadStudioActivity;
use App\Gallery\Actions\ReadAfterDeliveryColumn;
use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\User;
use App\Payments\Actions\ReadPaymentColumn;
use App\Payments\Enums\PaymentInstallmentStatus;
use App\Payments\Models\PaymentInstallment;
use App\Quotes\Enums\QuoteStatus;
use App\Quotes\Models\Quote;
use App\Quotes\Screens\ReadInquiryColumn;
use App\Quotes\Support\QuoteAcceptanceRate;
use App\Quotes\Support\QuotePipeline;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Übersicht: the figures with last year beside them, the next dates, the
 * open inquiries and projects, what is owed, and what moved.
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

    public function __invoke(
        Request $request,
        ReadInquiryColumn $inquiries,
        ReadPaymentColumn $payments,
        ReadAfterDeliveryColumn $afterDelivery,
        ReadStudioActivity $activity,
        OpenLeads $leads,
        OpenCases $projects,
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
            'todo' => $inquiries($viewer),
            'dunning' => $payments($viewer),
            'leads' => $leads->queue(),
            'projects' => $projects->queue($viewer),
            'galleries' => $afterDelivery($viewer),
        ]);
    }

    private function visible(User $viewer)
    {
        return ClientCase::query()->visibleTo($viewer)->select('cases.id');
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
            ->with('case')
            ->whereIn('shoots.case_id', $this->visible($viewer)->booked())
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
            'what' => $shoot->case->name,
            'where' => $shoot->venue_name,
            'away' => false,
            'href' => '/auftraege/'.$shoot->case->public_id.'/shootings',
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
                ->with(['case', 'contact', 'assignee'])
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
            ->whereIn('case_id', $this->visible($viewer))
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
            'plan',
            fn ($query) => $query->whereIn('case_id', $this->visible($viewer)),
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
            ->whereIn('shoots.case_id', $this->visible($viewer)->booked())
            ->uncancelled()
            ->whereBetween('shoots.date', [$from->toDateString(), $to->toDateString()])
            ->count();
    }
}
