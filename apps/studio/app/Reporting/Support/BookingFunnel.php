<?php

namespace App\Reporting\Support;

use App\Crm\Enums\StageAnchor;
use App\Crm\Enums\StagePipeline;
use App\Crm\Models\Lead;
use App\Crm\Support\CrmVocabulary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * S-13: what the CRM has been collecting since P-01 and never showed anyone.
 *
 * `Lead.source`, `Lead.lost_reason` and `leads.referred_by_contact_id` are
 * written on capture and read by nothing. Every figure here is a query over
 * columns that already exist — this story adds no schema at all.
 *
 * Everything keys on the **capture** date, never the booking date. A lead
 * captured in November and booked in January belongs to November's intake on
 * both sides of the ratio; splitting it would let a month's booking rate
 * exceed 100%, the same rule S-11 established for the quote acceptance tile.
 */
class BookingFunnel
{
    /**
     * The key of the stage that means the lead was won. Read rather than
     * hardcoded: a studio may rename "Gebucht", and the funnel is counted on
     * the anchor it carries. Null keeps the filter from matching anything,
     * which is the honest answer when no stage claims the anchor.
     */
    private static function wonKey(): ?string
    {
        return app(CrmVocabulary::class)->anchored(StagePipeline::Lead, StageAnchor::Won)?->key;
    }

    /**
     * Captured and booked per month for a window, oldest first.
     *
     * @return list<array{month: string, captured: int, booked: int, rate: int|null}>
     */
    public static function byMonth(Carbon $from, Carbon $to): array
    {
        $rows = Lead::query()
            ->selectRaw("to_char(date_trunc('month', leads.captured_at), 'YYYY-MM') as month")
            ->selectRaw('count(*) as captured')
            ->selectRaw('count(*) filter (where leads.stage = ?) as booked', [self::wonKey()])
            ->whereBetween('leads.captured_at', [$from, $to])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $rows->map(fn ($row) => [
            'month' => $row->month,
            'captured' => (int) $row->captured,
            'booked' => (int) $row->booked,
            // Null, not zero, on an empty denominator — S-11's rule. A month
            // with no intake had no booking rate, it did not have a bad one.
            'rate' => (int) $row->captured === 0 ? null : (int) round($row->booked / $row->captured * 100),
        ])->all();
    }

    /**
     * Where the work came from, and what it was worth. Revenue is the booked
     * project's payment plan total rather than its quote: the plan is what
     * the studio will actually invoice, and a quote can be accepted at one
     * figure and planned at another.
     *
     * "Sonstige" splits on its free-text detail: a bucket holding
     * Tierfotografie beside Firmenevent answers nothing.
     *
     * The label comes from LeadSource, not from a parallel i18n namespace:
     * the enum already names each case in German for the lead board, and a
     * second list of the same five words is a second thing to keep in step.
     *
     * @return list<array{source: string, label: string, captured: int, booked: int, revenue: int}>
     */
    public static function bySource(Carbon $from, Carbon $to): array
    {
        return Lead::query()
            ->selectRaw('leads.source')
            ->selectRaw('leads.source_other')
            ->selectRaw('count(*) as captured')
            ->selectRaw('count(*) filter (where leads.stage = ?) as booked', [self::wonKey()])
            ->selectRaw('coalesce(sum(payment_plans.total), 0) as revenue')
            ->leftJoin('jobs', 'jobs.project_id', '=', 'leads.project_id')
            ->leftJoin('payment_plans', 'payment_plans.job_id', '=', 'jobs.id')
            ->whereBetween('leads.captured_at', [$from, $to])
            ->groupBy('leads.source', 'leads.source_other')
            ->orderByDesc('captured')
            ->get()
            // `source` arrives as a LeadSource, not a string: Lead casts the
            // column, and a raw select does not opt out of that.
            ->map(fn ($row) => [
                // The detail rides in the key too: "Sonstige" split three ways
                // is three rows, and a row needs a key of its own.
                'source' => $row->source->value.($row->source_other === null ? '' : ':'.$row->source_other),
                'label' => $row->source_other === null
                    ? $row->source->label()
                    : $row->source->label().' — '.$row->source_other,
                'captured' => (int) $row->captured,
                'booked' => (int) $row->booked,
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }

    /**
     * Who sends the studio work — S-15's referral question, answerable by
     * name because C-04 records `referred_by_contact_id` rather than a free
     * text "empfohlen von".
     *
     * @return list<array{name: string, count: int}>
     */
    public static function referrers(Carbon $from, Carbon $to): array
    {
        return Lead::query()
            ->join('contacts', 'contacts.id', '=', 'leads.referred_by_contact_id')
            ->selectRaw('contacts.name, count(*) as count')
            ->whereBetween('leads.captured_at', [$from, $to])
            ->groupBy('contacts.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'count' => (int) $row->count])
            ->all();
    }

    /**
     * Why the studio lost the ones it lost.
     *
     * A lead lost without a reason is counted in the funnel above and absent
     * here, rather than bucketed as "unbekannt": inventing a category for a
     * field nobody filled in would make the breakdown look complete when it
     * is not.
     *
     * T-20 split the outcome off the free text, so the breakdown groups by it
     * too: a booking the studio itself declined is not a lost one, and the
     * two counted together read as a worse quota than the studio has.
     *
     * @return list<array{outcome: string, outcomeLabel: string, reason: string, count: int}>
     */
    public static function lostReasons(Carbon $from, Carbon $to): array
    {
        return Lead::query()
            ->select('outcome', 'lost_reason', DB::raw('count(*) as count'))
            ->whereNotNull('outcome')
            ->whereNotNull('lost_reason')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('outcome', 'lost_reason')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'outcome' => $row->outcome->value,
                'outcomeLabel' => $row->outcome->label(),
                'reason' => $row->lost_reason,
                'count' => (int) $row->count,
            ])
            ->all();
    }
}
