<?php

namespace App\Reporting\Http\Controllers;

use App\Crm\Enums\ProjectType;
use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Reporting\Enums\ReportPeriod;
use App\Reporting\Support\BookingFunnel;
use App\Reporting\Support\CashFlowForecast;
use App\Reporting\Support\FunnelTimes;
use App\Reporting\Support\QuoteAcceptanceRate;
use App\Reporting\Support\RevenueReport;
use App\Reporting\Support\ShootReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * S-13's Auswertungen, with M-07's Liquiditätsplanung as one of its panels
 * rather than a screen of its own — a studio asking "how are we doing" and a
 * studio asking "what is coming in" is the same studio at the same moment.
 *
 * Zeitraum und Projekttyp stehen in der URL, nicht in einer Session: eine
 * ausgewertete Ansicht ist ein Link, den man schicken und neu laden kann.
 * Der Vergleichszeitraum ist immer der gleich lange davor — siehe
 * ReportPeriod.
 *
 * Authority: ViewAllCases. These figures aggregate the whole studio's
 * book, so a second shooter who only reaches the projects they are crewed on
 * has no business reading them — and a per-role filtered aggregate would be
 * a number that means something different depending who asks, which is worse
 * than no number.
 */
class ReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(
            auth('staff')->user()?->can(Capability::ViewAllCases->value) ?? false,
            403,
        );

        $period = ReportPeriod::tryFrom((string) $request->query('zeitraum')) ?? ReportPeriod::TwelveMonths;
        $type = ProjectType::tryFrom((string) $request->query('typ'));

        ['from' => $from, 'to' => $to] = $period->window();
        ['from' => $priorFrom, 'to' => $priorTo] = $period->priorWindow();

        return Inertia::render('Auswertungen', [
            'window' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            'filters' => ['period' => $period->value, 'type' => $type?->value],
            'periods' => array_map(
                fn (ReportPeriod $case) => ['value' => $case->value, 'label' => $case->label()],
                ReportPeriod::cases(),
            ),
            'projectTypes' => array_map(
                fn (ProjectType $case) => ['value' => $case->value, 'label' => $case->label()],
                ProjectType::cases(),
            ),

            'funnelByMonth' => BookingFunnel::byMonth($from, $to, $type),
            'sources' => BookingFunnel::bySource($from, $to, $type),
            'referrers' => BookingFunnel::referrers($from, $to, $type),
            'lostReasons' => BookingFunnel::lostReasons($from, $to, $type),

            'acceptanceRate' => QuoteAcceptanceRate::between($from, $to, $type),
            'acceptanceRatePrior' => QuoteAcceptanceRate::between($priorFrom, $priorTo, $type),

            'revenue' => RevenueReport::forWindow($from, $to, $type),
            'revenuePrior' => RevenueReport::forWindow($priorFrom, $priorTo, $type),
            'perContact' => RevenueReport::perContact($from, $to, $type),
            'revenueByType' => RevenueReport::perProjectType($from, $to),

            'cashFlow' => CashFlowForecast::build(type: $type),
            'cashFlowPast' => CashFlowForecast::past($type),

            'steps' => FunnelTimes::steps($from, $to, $type),
            'dwell' => FunnelTimes::dwell($from, $to, $type),
            'responseTimes' => FunnelTimes::responseTimes($from, $to, $type),

            'shootsByMonth' => ShootReport::byMonth($from, $to, $type),
            'leadTimes' => ShootReport::leadTimes($from, $to, $type),
        ]);
    }
}
