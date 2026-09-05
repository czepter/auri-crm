<?php

namespace App\Reporting\Support;

use App\Crm\Enums\ProjectContactRole;
use App\Crm\Models\Contact;
use App\Payments\Models\PaymentPlan;
use Illuminate\Support\Carbon;

/**
 * S-13's money half: what a booked project is worth, and what a client is
 * worth across all of theirs.
 *
 * Reads payment plan totals rather than quote totals. A quote is what the
 * studio offered; a plan is what it will actually invoice, and by the time a
 * project is booked the second is the true one.
 */
class RevenueReport
{
    /**
     * Booked projects and their totals for a window, keyed on the booking
     * date — the moment the money became an obligation rather than a hope.
     *
     * @return array{projects: int, revenue: int, averagePerProject: int|null}
     */
    public static function forWindow(Carbon $from, Carbon $to): array
    {
        $plans = PaymentPlan::query()
            ->join('jobs', 'jobs.id', '=', 'payment_plans.job_id')
            ->whereBetween('jobs.booked_at', [$from, $to])
            ->get(['payment_plans.total']);

        $revenue = (int) $plans->sum('total');
        $count = $plans->count();

        return [
            'projects' => $count,
            'revenue' => $revenue,
            // Null on nothing booked, the same rule the rates follow: an
            // average of no projects is not €0.
            'averagePerProject' => $count === 0 ? null : (int) round($revenue / $count),
        ];
    }

    /**
     * Umsatz je Kunde, best first. Scoped to the primary contact of each
     * booked project — a partner or a planner on the same project is not a
     * second paying client, and counting them as one would double the
     * studio's revenue.
     *
     * @return list<array{name: string, projects: int, revenue: int}>
     */
    public static function perContact(Carbon $from, Carbon $to, int $limit = 10): array
    {
        return Contact::query()
            ->join('project_contacts', 'project_contacts.contact_id', '=', 'contacts.id')
            ->join('jobs', 'jobs.project_id', '=', 'project_contacts.project_id')
            ->leftJoin('payment_plans', 'payment_plans.job_id', '=', 'jobs.id')
            ->where('project_contacts.role', ProjectContactRole::Primary->value)
            ->whereBetween('jobs.booked_at', [$from, $to])
            ->groupBy('contacts.id', 'contacts.name')
            ->selectRaw('contacts.name, count(distinct jobs.id) as projects, coalesce(sum(payment_plans.total), 0) as revenue')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'projects' => (int) $row->projects,
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }
}
